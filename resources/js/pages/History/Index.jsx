import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import PageHeader from '@/components/app/PageHeader';
import Pagination from '@/components/app/Pagination';
import { shortText } from '@/lib/text';

/**
 * Historique (React) : dernières actions sur les dossiers dont l'utilisateur
 * peut consulter l'historique. Droits et contenus masqués calculés par Laravel
 * (Dossier::historyVisibleTo, HistoryExporter::row).
 */
const field =
    'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900';
const label = 'mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300';

export default function Index({ search: appliedSearch, filters, users, roles, logs, urls }) {
    const [search, setSearch] = useState(appliedSearch ?? '');
    const [user, setUser] = useState(filters.user);
    const [role, setRole] = useState(filters.role);
    const [kind, setKind] = useState(filters.kind);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const rows = logs.data ?? [];
    const active = appliedSearch || filters.user || filters.role || filters.kind || filters.from || filters.to;

    useEffect(() => setSearch(appliedSearch ?? ''), [appliedSearch]);

    const submit = (event) => {
        event.preventDefault();
        const params = { search: search.trim(), user, role, kind, from, to };
        Object.keys(params).forEach((key) => !params[key] && delete params[key]);
        router.get(urls.index, params, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Historique" />

            <div className="list-page">
            <PageHeader
                title="Historique"
                kicker="Traçabilité"
                subtitle="Dernières actions sur les dossiers dont vous pouvez consulter l’historique. Ouvrez un dossier pour son historique complet et ses exports PDF / Word."
            />

            <form onSubmit={submit} className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                <div>
                    <label htmlFor="history-search" className={label}>Dossier</label>
                    <input id="history-search" name="search" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Référence ou objet" className={field} />
                </div>
                {users.length > 0 && (
                    <div>
                        <label htmlFor="history-user" className={label}>Utilisateur</label>
                        <select id="history-user" value={user} onChange={(event) => setUser(event.target.value)} className={field}>
                            <option value="">Tous</option>
                            {users.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
                        </select>
                    </div>
                )}
                {roles.length > 0 && (
                    <div>
                        <label htmlFor="history-role" className={label}>Rôle</label>
                        <select id="history-role" value={role} onChange={(event) => setRole(event.target.value)} className={field}>
                            <option value="">Tous</option>
                            {roles.map((name) => <option key={name} value={name}>{name}</option>)}
                        </select>
                    </div>
                )}
                <div>
                    <label htmlFor="history-kind" className={label}>Type d’action</label>
                    <select id="history-kind" value={kind} onChange={(event) => setKind(event.target.value)} className={field}>
                        <option value="">Toutes</option>
                        <option value="deletion">Suppressions</option>
                    </select>
                </div>
                <div>
                    <label htmlFor="history-from" className={label}>Du</label>
                    <input id="history-from" type="date" value={from} onChange={(event) => setFrom(event.target.value)} className={field} />
                </div>
                <div>
                    <label htmlFor="history-to" className={label}>Au</label>
                    <input id="history-to" type="date" value={to} onChange={(event) => setTo(event.target.value)} className={field} />
                </div>
                <div className="flex items-end gap-3">
                    <button type="submit" className="btn-secondary py-2.5">Filtrer</button>
                    {active && <Link href={urls.index} className="btn-secondary py-2.5">Effacer</Link>}
                </div>
            </form>

            <div className="card list-scroll p-0">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Dossier</th>
                            <th>Utilisateur</th>
                            <th>Rôle</th>
                            <th>Action</th>
                            <th>Résultat</th>
                            <th>Ancienne → nouvelle valeur</th>
                            <th>Commentaire</th>
                            <th className="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id}>
                                <td data-label="Date" className="whitespace-nowrap">
                                    {row.date}
                                </td>
                                <td data-label="Heure">{row.time}</td>
                                <td data-label="Dossier" className="min-w-[15rem]">
                                    <a href={row.dossier.url} className="font-mono font-bold whitespace-nowrap text-brand-500 hover:underline dark:text-brand-300" title={row.dossier.subject}>
                                        {row.dossier.reference}
                                    </a>
                                    <span className="block text-xs whitespace-nowrap text-univ-muted dark:text-slate-400" title={row.dossier.subject}>
                                        {shortText(row.dossier.subject)}
                                    </span>
                                </td>
                                <td data-label="Utilisateur" className="whitespace-nowrap">
                                    {row.user}
                                </td>
                                <td data-label="Rôle" className="whitespace-nowrap">{row.role}</td>
                                <td data-label="Action" className="font-medium">
                                    {row.action}
                                </td>
                                <td data-label="Résultat">{row.result}</td>
                                <td data-label="Ancienne → nouvelle" className="text-xs text-slate-600 dark:text-slate-400">
                                    {row.details}
                                </td>
                                <td data-label="Commentaire" className="text-xs">
                                    {row.comment}
                                </td>
                                <td className="text-right whitespace-nowrap">
                                    <a href={row.dossier.url} className="btn-secondary min-h-8 px-3 py-1.5 text-xs" aria-label={`Voir le dossier ${row.dossier.reference}`}>
                                        <Eye className="size-4" aria-hidden="true" /> Voir
                                    </a>
                                </td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={10} className="py-10 text-center text-slate-500">
                                    Aucune action enregistrée.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="mt-4">
                <Pagination paginator={logs} />
            </div>
            </div>
        </>
    );
}
