import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import DossierTable from '@/components/app/DossierTable';
import { cleanParams } from '@/components/app/DossierFilters';
import PageHeader from '@/components/app/PageHeader';

const REMOVALS = [
    { value: '', label: 'Tous les dossiers' },
    { value: 'any', label: 'Retirés par l’expéditeur (corbeille ou supprimés)' },
    { value: 'trashed', label: 'Dans la corbeille de l’expéditeur' },
    { value: 'purged', label: 'Supprimés définitivement' },
];

const fieldClass =
    'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-900';

/**
 * Suivi des dossiers (administrateur) : tous les dossiers qui circulent, en
 * lecture seule, y compris ceux que l'expéditeur a supprimés définitivement
 * de son espace (Admin\DossierMonitorController).
 */
export default function Index({ filters, statuses, counts, dossiers, urls }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [removal, setRemoval] = useState(filters.removal ?? '');

    const apply = (next) => router.get(urls.index, cleanParams({ search, status, removal, ...next }), { preserveScroll: true });

    return (
        <>
            <Head title="Suivi des dossiers" />

            <div className="list-page">
            <PageHeader
                title="Suivi des dossiers"
                subtitle={`${counts.all} dossier(s) en circulation · ${counts.trashed} dans une corbeille · ${counts.purged} supprimé(s) définitivement par leur expéditeur. Consultation seule.`}
            />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    apply({});
                }}
                className="mb-4 flex flex-wrap items-end gap-3"
            >
                <div className="w-full sm:w-72">
                    <label htmlFor="search" className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Rechercher</label>
                    <input id="search" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Référence, objet ou expéditeur" className={fieldClass} />
                </div>
                <div className="w-full sm:w-44">
                    <label htmlFor="status" className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Statut du dossier</label>
                    <select id="status" value={status} onChange={(event) => { setStatus(event.target.value); apply({ status: event.target.value }); }} className={fieldClass}>
                        <option value="">Tous</option>
                        {statuses.map((option) => (
                            <option key={option.value} value={option.value}>{option.label}</option>
                        ))}
                    </select>
                </div>
                <div className="w-full sm:w-80">
                    <label htmlFor="removal" className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Suppression par l’expéditeur</label>
                    <select id="removal" value={removal} onChange={(event) => { setRemoval(event.target.value); apply({ removal: event.target.value }); }} className={fieldClass}>
                        {REMOVALS.map((option) => (
                            <option key={option.value} value={option.value}>{option.label}</option>
                        ))}
                    </select>
                </div>
                <button type="submit" className="btn-secondary py-2.5">Filtrer</button>
            </form>

            <DossierTable dossiers={dossiers} />
            </div>
        </>
    );
}
