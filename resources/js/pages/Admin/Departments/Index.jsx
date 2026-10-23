import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Ban, Building2, CircleCheck, Eye, Pencil, Plus, RotateCcw, Users } from 'lucide-react';
import ConfirmDialog from '@/components/app/ConfirmDialog';
import LiveSearch from '@/components/app/LiveSearch';
import PageHeader from '@/components/app/PageHeader';
import StatusBadge from '@/components/app/StatusBadge';
import { cn } from '@/lib/utils';

/** Tuile de total : lien si l'utilisateur peut gérer les comptes, sinon simple encadré. */
function Tile({ href, tone, Icon, label, value, testId }) {
    const tones = {
        emerald: ['bg-emerald-50 dark:bg-emerald-500/10', 'bg-emerald-500 shadow-emerald-500/40', 'text-emerald-600 dark:text-emerald-300'],
        brand: ['bg-brand-50 dark:bg-brand-500/10', 'bg-brand-500 shadow-brand-500/40', 'text-brand-600 dark:text-brand-300'],
        amber: ['bg-amber-50 dark:bg-amber-500/10', 'bg-amber-500 shadow-amber-500/40', 'text-amber-600 dark:text-amber-300'],
    }[tone];
    const content = (
        <>
            <span className={cn('inline-flex size-9 items-center justify-center rounded-full text-white shadow-lg', tones[1])}>
                <Icon className="size-5" aria-hidden="true" />
            </span>
            <span className={cn('text-xs font-medium', tones[2])}>{label}</span>
            <span className={cn('text-xl font-bold tabular-nums', tones[2])} data-testid={testId}>
                {value}
            </span>
        </>
    );

    return href ? (
        <a href={href} className={cn('stat-tile', tones[0])}>
            {content}
        </a>
    ) : (
        <div className={cn('stat-tile cursor-default hover:translate-y-0 hover:shadow-none', tones[0])}>{content}</div>
    );
}

/** Modifier et Désactiver / Réactiver (administrateur) ; la désactivation demande confirmation. */
function RowActions({ department }) {
    const [confirming, setConfirming] = useState(false);
    const [busy, setBusy] = useState(false);
    const toggle = () => {
        setBusy(true);
        router.patch(department.toggleUrl, {}, { preserveScroll: true, onFinish: () => { setBusy(false); setConfirming(false); } });
    };

    return (
        <div className="flex flex-wrap justify-end gap-2 sm:flex-nowrap">
            <a href={department.editUrl} className="btn-secondary p-2" title="Modifier / Responsable" aria-label="Modifier / Responsable">
                <Pencil className="size-4" aria-hidden="true" />
            </a>
            <button
                type="button"
                disabled={busy}
                onClick={() => (department.active ? setConfirming(true) : toggle())}
                className={cn('p-2', department.active ? 'btn-danger' : 'btn-success')}
                title={department.active ? 'Désactiver' : 'Réactiver'}
                aria-label={department.active ? 'Désactiver' : 'Réactiver'}
            >
                {department.active ? <Ban className="size-4" aria-hidden="true" /> : <RotateCcw className="size-4" aria-hidden="true" />}
            </button>
            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                danger
                Icon={Ban}
                title="Désactiver le département"
                question={`Désactiver le département ${department.name} ? Il conserve ses employés, dossiers, documents et historique.`}
                confirmLabel="Désactiver"
                onConfirm={toggle}
                processing={busy}
            />
        </div>
    );
}

/**
 * Départements (React) : gestion pour l'administrateur (ajout, modification,
 * désactivation), lecture seule pour les responsables et le RH avec leurs
 * envois par département. Droits calculés par Laravel (DepartmentPolicy).
 */
export default function Index({ can, totals, cards, departments, search: appliedSearch, urls }) {
    const [search, setSearch] = useState(appliedSearch ?? '');
    useEffect(() => setSearch(appliedSearch ?? ''), [appliedSearch]);

    const submit = (event) => {
        event.preventDefault();
        router.get(urls.index, search.trim() ? { search } : {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Départements" />

            <PageHeader
                title="Départements"
                subtitle="Un département désactivé conserve ses employés, dossiers, documents et historique."
                actions={
                    can.create ? (
                        <a href={urls.create} className="btn-primary">
                            <Plus className="size-4" aria-hidden="true" /> Ajout département
                        </a>
                    ) : (
                        <span className="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            <Eye className="size-3.5" aria-hidden="true" /> Lecture seule
                        </span>
                    )
                }
            />

            {/* Totaux */}
            <div className="mb-4 grid gap-3 sm:grid-cols-3">
                <Tile href={can.manageUsers ? urls.activeUsers : null} tone="emerald" Icon={CircleCheck} label="Comptes actifs" value={totals.active} testId="total-active" />
                <Tile href={can.manageUsers ? urls.users : null} tone="brand" Icon={Users} label="Utilisateurs (tous comptes)" value={totals.users} />
                <Tile tone="amber" Icon={Building2} label="Départements actifs" value={totals.departments} />
            </div>

            {/* Cartes par département */}
            <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                {cards.map((card) => {
                    const body = (
                        <>
                            <span className="truncate text-xs font-medium text-slate-600 dark:text-slate-300">{card.name}</span>
                            <span className={cn('text-xl font-bold text-slate-800 tabular-nums dark:text-white', can.manageUsers && 'group-hover:text-brand-600')}>{card.usersCount}</span>
                            <span className="text-xs text-slate-500 dark:text-slate-400">
                                utilisateur(s) · <span className="font-medium text-emerald-600 dark:text-emerald-400">{card.activeUsersCount} actif(s)</span>
                            </span>
                        </>
                    );

                    return can.manageUsers ? (
                        <a
                            key={card.id}
                            href={card.usersUrl}
                            title={`Voir les utilisateurs du département ${card.name}`}
                            className={cn('card group flex flex-col gap-0.5 p-3 transition hover:-translate-y-0.5 hover:border-brand-300 dark:hover:border-brand-700', !card.active && 'opacity-60')}
                        >
                            {body}
                        </a>
                    ) : (
                        <div key={card.id} className={cn('card flex flex-col gap-0.5 p-3', !card.active && 'opacity-60')}>
                            {body}
                        </div>
                    );
                })}
            </div>

            {/* Recherche (propositions instantanées, comme sur les autres listes) */}
            <form onSubmit={submit} className="mb-4 flex flex-wrap items-end gap-3">
                <div className="w-full sm:w-80">
                    <LiveSearch label="Rechercher" placeholder="Nom ou code du département" value={search} onChange={setSearch} suggestUrl={urls.index} />
                </div>
                <button type="submit" className="btn-secondary py-2.5">
                    Filtrer
                </button>
                {appliedSearch && (
                    <Link href={urls.index} className="btn-secondary py-2.5">
                        Effacer
                    </Link>
                )}
            </form>

            <div className="card overflow-x-auto p-0">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Responsable</th>
                            <th>Employés actifs</th>
                            <th>Dossiers</th>
                            {!can.create && <th>Mes envois</th>}
                            <th>Statut</th>
                            <th className="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {departments.map((department) => (
                            <tr key={department.id}>
                                <td data-label="Nom" className="font-medium">
                                    {department.name} <span className="ml-1 font-mono text-xs text-slate-400">{department.code}</span>
                                </td>
                                <td data-label="Responsable">{department.managerName ?? '—'}</td>
                                <td data-label="Employés actifs">
                                    {can.manageUsers ? (
                                        <a href={department.usersUrl} className="font-medium text-brand-600 hover:underline dark:text-brand-300" title="Voir les utilisateurs du département">
                                            {department.activeUsersCount}
                                        </a>
                                    ) : (
                                        <span className="font-medium">{department.activeUsersCount}</span>
                                    )}
                                </td>
                                <td data-label="Dossiers">{department.dossiersCount}</td>
                                {!can.create && (
                                    <td data-label="Mes envois">
                                        <a href={department.sentUrl} className="font-medium text-brand-600 hover:underline dark:text-brand-300" title={`Voir les dossiers que j’ai envoyés à ${department.name}`}>
                                            {department.sent} dossier(s)
                                        </a>
                                    </td>
                                )}
                                <td data-label="Statut">{department.active ? <StatusBadge color="green">Actif</StatusBadge> : <StatusBadge color="red">Désactivé</StatusBadge>}</td>
                                <td>{department.canUpdate && <RowActions department={department} />}</td>
                            </tr>
                        ))}
                        {departments.length === 0 && (
                            <tr>
                                <td colSpan={7} className="py-8 text-center text-slate-500">
                                    Aucun département ne correspond à « {appliedSearch} ».
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}
