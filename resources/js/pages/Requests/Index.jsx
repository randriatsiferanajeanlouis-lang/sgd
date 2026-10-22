import { Head, Link, router } from '@inertiajs/react';
import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import { ArrowDown, ArrowUp, ArrowUpDown, Eye, FileSpreadsheet, FolderOpen, Inbox, Paperclip, Plus, Search, Settings2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { paginationLabel, shortText } from '@/lib/text';
import { cn } from '@/lib/utils';

/** Couleurs des statuts (mêmes teintes que les badges Blade). */
const tones = {
    blue: 'bg-blue-600 text-white dark:bg-blue-500',
    amber: 'bg-amber-500 text-white dark:bg-amber-500 dark:text-slate-950',
    green: 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-slate-950',
    slate: 'bg-slate-500 text-white dark:bg-slate-600',
    red: 'bg-rose-600 text-white',
};
const softTones = {
    blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
    amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
    green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    slate: 'bg-slate-500/10 text-slate-600 dark:text-slate-300',
};

/** Colonnes masquables (réglages ⚙, mémorisés dans le navigateur). */
const COLUMNS = [
    { key: 'recipients', label: 'Destinataires' },
    { key: 'status', label: 'Statut' },
    { key: 'changed', label: 'Modifié le / par' },
    { key: 'created', label: 'Créée le' },
    { key: 'due', label: 'Échéance' },
    { key: 'documents', label: 'Pièces' },
];
const STORAGE_KEY = 'requests.columns';
const ALL = 'all';

function readColumns() {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null');

        return Array.isArray(saved) ? saved : COLUMNS.map((column) => column.key);
    } catch {
        return COLUMNS.map((column) => column.key);
    }
}

/**
 * « Demande » : liste des demandes envoyées, présentée comme une liste de
 * documents (titre et compteur, « Nouvelle demande », « Voir les dossiers », réglages, export, tri,
 * regroupement par statut). Données : DossierController@requests.
 */
export default function RequestsIndex({ dossiers, counts, statuses, filters, urls }) {
    const [visible, setVisible] = useState(readColumns);
    const [search, setSearch] = useState(filters.search);
    const firstRender = useRef(true);

    const total = Object.values(counts).reduce((sum, count) => sum + count, 0);
    const rows = dossiers.data;
    const show = (key) => visible.includes(key);
    const columnCount = 1 + visible.length + 1;

    const visit = (changes) =>
        router.get(urls.index, cleanQuery({ ...filters, group: filters.group ? undefined : '0', ...changes }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    // Recherche instantanée (après une courte pause de frappe).
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return undefined;
        }

        const timer = setTimeout(() => visit({ search, page: undefined }), 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const toggleColumn = (key, checked) => {
        const next = checked ? [...visible, key] : visible.filter((item) => item !== key);
        setVisible(next);
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
        } catch {
            // Stockage indisponible : le réglage vaut pour cette page seulement.
        }
    };

    const sortBy = (column) => {
        const dir = filters.sort === column && filters.dir === 'asc' ? 'desc' : 'asc';
        visit({ sort: column, dir, page: undefined });
    };

    // Regroupement par statut, dans l'ordre des statuts.
    const groups = useMemo(() => {
        if (!filters.group) {
            return [{ status: null, rows }];
        }

        return statuses
            .map((status) => ({ status, rows: rows.filter((row) => row.status.value === status.value) }))
            .filter((group) => group.rows.length > 0);
    }, [rows, statuses, filters.group]);

    return (
        <>
            <Head title="Mes demandes" />

            <div className="overflow-hidden rounded-xl border bg-card text-card-foreground shadow-xs">
                {/* En-tête : titre + compteur, barre d'actions à droite */}
                <div className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3 sm:px-5">
                    <div className="min-w-0">
                        <h1 className="text-lg font-bold tracking-tight sm:text-xl">
                            Mes demandes <span className="font-medium text-muted-foreground">({total})</span>
                        </h1>
                        <p className="text-xs text-muted-foreground">Demandes internes que vous avez envoyées, regroupées par statut.</p>
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5">
                        <Button asChild size="sm">
                            <a href={urls.create}><Plus /> Nouvelle demande</a>
                        </Button>
                        {urls.dossiers && (
                            <Button asChild variant="outline" size="sm">
                                <a href={urls.dossiers}><FolderOpen /> Voir les dossiers</a>
                            </Button>
                        )}


                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="icon" className="size-8" aria-label="Réglages de l’affichage"><Settings2 /></Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuLabel>Affichage</DropdownMenuLabel>
                                <DropdownMenuCheckboxItem checked={filters.group} onCheckedChange={(checked) => visit({ group: checked ? undefined : '0' })}>
                                    Regrouper par statut
                                </DropdownMenuCheckboxItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel>Colonnes</DropdownMenuLabel>
                                {COLUMNS.map((column) => (
                                    <DropdownMenuCheckboxItem key={column.key} checked={show(column.key)} onCheckedChange={(checked) => toggleColumn(column.key, checked)} onSelect={(event) => event.preventDefault()}>
                                        {column.label}
                                    </DropdownMenuCheckboxItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <Button asChild variant="outline" size="sm" title="Exporter en CSV (Excel)">
                            <a href={`${urls.export}${queryString(filters)}`}><FileSpreadsheet /> <span className="hidden sm:inline">Exporter</span></a>
                        </Button>
                    </div>
                </div>

                {/* Recherche et filtre */}
                <div className="flex flex-wrap items-center gap-2 border-b bg-muted/30 px-4 py-2.5 sm:px-5">
                    <div className="relative w-full sm:w-80">
                        <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Référence, objet ou destinataire…" className="h-9 bg-background pl-8" aria-label="Rechercher une demande" />
                    </div>
                    <Select value={filters.status || ALL} onValueChange={(value) => visit({ status: value === ALL ? undefined : value, page: undefined })}>
                        <SelectTrigger className="h-9 w-44 bg-background" aria-label="Filtrer par statut"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Tous les statuts</SelectItem>
                            {statuses.map((status) => <SelectItem key={status.value} value={status.value}>{status.label} ({counts[status.value]})</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow className="bg-muted/40 hover:bg-muted/40">
                            <SortHead column="subject" filters={filters} onSort={sortBy} className="pl-4 sm:pl-5">Demande</SortHead>
                            {show('recipients') && <TableHead className="hidden lg:table-cell">Destinataires</TableHead>}
                            {show('status') && <TableHead>Statut</TableHead>}
                            {show('changed') && <SortHead column="updated_at" filters={filters} onSort={sortBy} className="hidden md:table-cell">Modifié le / par</SortHead>}
                            {show('created') && <SortHead column="created_at" filters={filters} onSort={sortBy} className="hidden md:table-cell">Créée le</SortHead>}
                            {show('due') && <SortHead column="due_at" filters={filters} onSort={sortBy} className="hidden sm:table-cell">Échéance</SortHead>}
                            {show('documents') && <TableHead className="hidden xl:table-cell">Pièces</TableHead>}
                            <TableHead className="pr-4 text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.length === 0 && (
                            <TableRow className="hover:bg-transparent">
                                <TableCell colSpan={columnCount} className="py-16 text-center">
                                    <Inbox className="mx-auto mb-3 size-10 text-muted-foreground/50" aria-hidden="true" />
                                    <p className="font-semibold">{filters.search || filters.status ? 'Aucune demande ne correspond à la recherche.' : 'Vous n’avez encore envoyé aucune demande.'}</p>
                                </TableCell>
                            </TableRow>
                        )}

                        {groups.map((group) => (
                            <Fragment key={group.status?.value ?? 'all'}>
                                {group.status && (
                                    <TableRow className="bg-muted/60 hover:bg-muted/60">
                                        <TableCell colSpan={columnCount} className="py-2 pl-4 text-[13px] font-semibold sm:pl-5">
                                            Statut : {group.status.label}
                                            <span className="ml-1.5 font-normal text-muted-foreground">({counts[group.status.value]})</span>
                                        </TableCell>
                                    </TableRow>
                                )}
                                {group.rows.map((row) => (
                                    <TableRow
                                        key={row.id}
                                        className="cursor-pointer"
                                        onClick={(event) => {
                                            if (!event.target.closest('a, button')) {
                                                window.location.assign(row.showUrl);
                                            }
                                        }}
                                    >
                                        <TableCell className="max-w-[22rem] py-3.5 pl-4 sm:pl-5">
                                            <a href={row.showUrl} title={row.subject} className="block font-semibold whitespace-nowrap hover:text-primary hover:underline">{shortText(row.subject)}</a>
                                            <div className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                                                <span className="font-mono">{row.reference}</span>
                                                <span aria-hidden="true">·</span>
                                                <span className="truncate">{row.type}</span>
                                            </div>
                                        </TableCell>
                                        {show('recipients') && (
                                            <TableCell className="hidden max-w-[16rem] lg:table-cell">
                                                <div className="flex flex-wrap gap-1">
                                                    {row.recipients.map((recipient) => (
                                                        <span key={recipient.name} title={recipient.status} className={cn('inline-flex rounded-md px-1.5 py-0.5 text-[11px] font-medium', softTones[recipient.color] ?? softTones.slate)}>
                                                            {recipient.name}
                                                        </span>
                                                    ))}
                                                </div>
                                            </TableCell>
                                        )}
                                        {show('status') && (
                                            <TableCell>
                                                <span className={cn('inline-flex rounded px-1.5 py-0.5 text-[11px] font-bold', tones[row.status.color] ?? tones.slate)}>{row.status.label}</span>
                                            </TableCell>
                                        )}
                                        {show('changed') && (
                                            <TableCell className="hidden text-[13px] md:table-cell">
                                                <div className="tabular-nums">{row.changedAt}</div>
                                                <div className="text-muted-foreground">{row.changedBy}</div>
                                            </TableCell>
                                        )}
                                        {show('created') && <TableCell className="hidden text-[13px] tabular-nums md:table-cell">{row.createdAt}</TableCell>}
                                        {show('due') && (
                                            <TableCell className="hidden text-[13px] sm:table-cell">
                                                <div className="tabular-nums">{row.dueAt ?? '—'}</div>
                                                {row.deadline && (
                                                    <div className={cn('text-xs', row.deadline.overdue ? 'font-semibold text-destructive' : 'text-muted-foreground')}>{row.deadline.label}</div>
                                                )}
                                            </TableCell>
                                        )}
                                        {show('documents') && (
                                            <TableCell className="hidden xl:table-cell">
                                                {row.documents > 0 ? (
                                                    <a href={`${row.showUrl}#documents-title`} className="inline-flex items-center gap-1 text-[13px] font-medium text-primary hover:underline">
                                                        <Paperclip className="size-3.5" /> {row.documents} pièce{row.documents > 1 ? 's' : ''}
                                                    </a>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </TableCell>
                                        )}
                                        <TableCell className="pr-4 text-right">
                                            <Button asChild variant="outline" size="sm" className="h-8">
                                                <a href={row.showUrl} aria-label={`Voir le dossier ${row.reference}`}><Eye /> Voir</a>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </Fragment>
                        ))}
                    </TableBody>
                </Table>

                {dossiers.last_page > 1 && (
                    <nav className="flex flex-wrap items-center justify-between gap-2 border-t px-4 py-3 sm:px-5" aria-label="Pagination">
                        <span className="text-xs text-muted-foreground">{dossiers.from}–{dossiers.to} sur {dossiers.total}</span>
                        <div className="flex flex-wrap gap-1">
                            {dossiers.links.map((link, index) =>
                                link.url ? (
                                    <Button key={index} asChild size="sm" variant={link.active ? 'default' : 'outline'} className="h-8">
                                        <Link href={link.url} preserveScroll preserveState>{paginationLabel(link.label)}</Link>
                                    </Button>
                                ) : (
                                    <Button key={index} size="sm" variant="outline" className="h-8" disabled>{paginationLabel(link.label)}</Button>
                                ),
                            )}
                        </div>
                    </nav>
                )}
            </div>

        </>
    );
}

function SortHead({ column, filters, onSort, className, children }) {
    const active = filters.sort === column;
    const Icon = !active ? ArrowUpDown : filters.dir === 'asc' ? ArrowUp : ArrowDown;

    return (
        <TableHead className={className} aria-sort={active ? (filters.dir === 'asc' ? 'ascending' : 'descending') : 'none'}>
            <button type="button" onClick={() => onSort(column)} className={cn('-ml-1 inline-flex items-center gap-1 rounded px-1 py-0.5 hover:bg-muted', active && 'text-foreground')}>
                {children}
                <Icon className={cn('size-3.5', !active && 'opacity-40')} aria-hidden="true" />
            </button>
        </TableHead>
    );
}

function cleanQuery(query) {
    return Object.fromEntries(Object.entries(query).filter(([key, value]) => value !== undefined && value !== '' && value !== null && !(key === 'group' && value === true)));
}

function queryString(filters) {
    const params = new URLSearchParams(cleanQuery({ search: filters.search, status: filters.status, sort: filters.sort, dir: filters.dir }));
    const text = params.toString();

    return text ? `?${text}` : '';
}
