import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import LiveSearch from '@/components/app/LiveSearch';

/** Paramètres d'adresse sans les valeurs vides (comme les liens Blade : array_filter). */
export const cleanParams = (params) => Object.fromEntries(Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== ''));

/**
 * Filtres d'une liste de dossiers (équivalent de dossiers/partials/filters) :
 * recherche avec propositions instantanées et statut du dossier. `keep` :
 * paramètres conservés (boîte, département…) ; la liste est rechargée par Inertia.
 */
export default function DossierFilters({ url, filters, statuses, keep = {}, placeholder = 'Référence, objet ou expéditeur' }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    // Champs alignés sur les filtres réellement appliqués (changement de boîte, de page…).
    useEffect(() => {
        setSearch(filters.search ?? '');
        setStatus(filters.status ?? '');
    }, [filters.search, filters.status]);

    const submit = (event) => {
        event.preventDefault();
        router.get(url, cleanParams({ ...keep, search, status }), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="mb-4 flex flex-wrap items-end gap-3">
            <div className="w-full sm:w-72">
                <LiveSearch label="Rechercher" placeholder={placeholder} value={search} onChange={setSearch} suggestUrl={url} params={{ ...keep, status }} />
            </div>
            <div className="w-full sm:w-48">
                <label htmlFor="status" className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                    Statut du dossier
                </label>
                <select
                    id="status"
                    name="status"
                    value={status}
                    onChange={(event) => setStatus(event.target.value)}
                    className="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-900"
                >
                    <option value="">Tous</option>
                    {statuses.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </div>
            <button type="submit" className="btn-secondary py-2.5">
                Filtrer
            </button>
        </form>
    );
}
