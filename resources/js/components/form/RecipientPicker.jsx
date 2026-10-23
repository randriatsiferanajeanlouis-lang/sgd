import { useMemo, useState } from 'react';
import { Info, Search } from 'lucide-react';
import { matchesSearch } from '@/lib/search';

/** Annuaire à plat : chaque personne avec le nom et l'identifiant de son département. */
export function flattenDirectory(directory) {
    return directory.flatMap((department) => department.users.map((user) => ({ ...user, department: department.name, departmentId: department.id })));
}

/** Libellé d'un destinataire, comme dans la confirmation Blade : « Nom (Département) ». */
export const recipientLabel = (user) => `${user.name} (${user.department})`;

/**
 * Choix des destinataires (équivalent de <x-recipient-picker>) : filtre par
 * département ou recherche par nom / fonction, pastilles des personnes choisies.
 * `selected` : identifiants choisis ; onChange reçoit la nouvelle liste.
 */
export default function RecipientPicker({ directory, selected, onChange, error, label = 'Destinataire(s)' }) {
    const [departmentId, setDepartmentId] = useState('');
    const [search, setSearch] = useState('');
    const people = useMemo(() => flattenDirectory(directory), [directory]);
    const chosen = selected.map((id) => people.find((user) => user.id === id)).filter(Boolean);

    const available =
        !departmentId && !search.trim()
            ? []
            : (departmentId ? people.filter((user) => String(user.departmentId) === String(departmentId)) : people).filter((user) =>
                  matchesSearch(`${user.name} ${user.job_title ?? ''}`, search),
              );

    const toggle = (user) => onChange(selected.includes(user.id) ? selected.filter((id) => id !== user.id) : [...selected, user.id]);

    const addFirst = () => {
        const user = available.find((item) => !selected.includes(item.id));
        if (user) {
            onChange([...selected, user.id]);
            setSearch('');
        }
    };

    return (
        <div data-recipient-picker className="@container space-y-3">
            <div className="flex items-center justify-between gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                <span>
                    {label} <span className="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                </span>
                <span className="text-slate-400" title="Filtrez par département ou tapez un nom, une fonction : chaque personne choisie reçoit la demande.">
                    <Info className="size-4" aria-hidden="true" />
                </span>
            </div>

            <div className="grid gap-3 @md:grid-cols-[minmax(0,13rem)_1fr]">
                <div>
                    <label htmlFor="recipient-department" className="mb-1.5 block text-xs text-slate-500">
                        Département destinataire
                    </label>
                    <select
                        id="recipient-department"
                        value={departmentId}
                        onChange={(event) => setDepartmentId(event.target.value)}
                        className="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <option value="">Tous les départements</option>
                        {directory.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label htmlFor="recipient-search" className="mb-1.5 block text-xs text-slate-500">
                        Rechercher
                    </label>
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input
                            id="recipient-search"
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    addFirst();
                                }
                            }}
                            autoComplete="off"
                            placeholder="Nom ou fonction…"
                            className="block w-full rounded-lg border border-slate-300 bg-white py-2 pr-3 pl-9 text-sm dark:border-slate-700 dark:bg-slate-900"
                        />
                    </div>
                </div>
            </div>

            <div className="max-h-48 overflow-y-auto rounded-lg border border-slate-300 dark:border-slate-700">
                {!departmentId && !search.trim() && <p className="px-3 py-2.5 text-sm text-slate-500">Choisissez un département ou tapez un nom.</p>}
                {(departmentId || search.trim()) && available.length === 0 && <p className="px-3 py-2.5 text-sm text-slate-500">Aucun utilisateur actif ne correspond.</p>}
                {available.map((user) => (
                    <label key={user.id} className="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                        <input type="checkbox" className="size-4" checked={selected.includes(user.id)} onChange={() => toggle(user)} />
                        <span>
                            <span className="font-medium">{user.name}</span>
                            {user.job_title && <span className="text-slate-500"> — {user.job_title}</span>}
                            {!departmentId && <span className="text-xs text-slate-400"> · {user.department}</span>}
                        </span>
                    </label>
                ))}
            </div>

            <div className="flex flex-wrap gap-2" aria-live="polite">
                {chosen.map((user) => (
                    <span
                        key={user.id}
                        className="inline-flex items-center gap-1.5 rounded-md border border-amber-300 bg-amber-100 px-2.5 py-1 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-100"
                    >
                        <button type="button" onClick={() => toggle(user)} className="leading-none font-semibold hover:text-red-600" aria-label={`Retirer ${user.name}`}>
                            ×
                        </button>
                        <span>{recipientLabel(user)}</span>
                    </span>
                ))}
                {chosen.length === 0 && <span className="text-sm text-slate-500">Aucun destinataire sélectionné.</span>}
            </div>

            {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
        </div>
    );
}
