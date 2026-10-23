import { useEffect, useMemo, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Building2, Search, Users } from 'lucide-react';
import PageHeader from '@/components/app/PageHeader';
import StatusBadge from '@/components/app/StatusBadge';
import UserAvatar from '@/components/app/UserAvatar';
import { matchesSearch } from '@/lib/search';

/**
 * Annuaire des départements (React) : une carte par département actif avec le
 * nombre de dossiers envoyés par l'utilisateur et, sur demande, son personnel
 * (nom et fonction uniquement, aucune donnée personnelle). La recherche filtre
 * départements et personnes, avec des propositions sous la barre.
 */
export default function Index({ departments }) {
    const [term, setTerm] = useState('');
    const [open, setOpen] = useState(false);
    const [expanded, setExpanded] = useState({});
    const [focusId, setFocusId] = useState(null);
    const box = useRef(null);
    const searching = term.trim() !== '';

    const people = useMemo(() => departments.flatMap((department) => department.members.map((member) => ({ ...member, department: department.name, departmentId: department.id }))), [departments]);
    const suggestions = people.filter((person) => matchesSearch(`${person.name} ${person.jobTitle ?? ''}`, term)).slice(0, 8);

    useEffect(() => {
        const outside = (event) => box.current && !box.current.contains(event.target) && setOpen(false);
        document.addEventListener('mousedown', outside);
        return () => document.removeEventListener('mousedown', outside);
    }, []);

    // Personne choisie dans les propositions : sa carte est dépliée puis la ligne est amenée à l'écran.
    useEffect(() => {
        if (focusId !== null) {
            document.querySelector(`[data-person-id="${focusId}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setFocusId(null);
        }
    }, [focusId, expanded]);

    const visibleMembers = (department) => department.members.filter((member) => matchesSearch(`${member.name} ${member.jobTitle ?? ''}`, term));
    const departmentVisible = (department) => !searching || matchesSearch(`${department.name} ${department.code}`, term) || visibleMembers(department).length > 0;

    return (
        <>
            <Head title="Départements" />

            <PageHeader title="Départements" subtitle="Chaque carte indique le nombre de dossiers que vous avez envoyés au département. Cliquez sur « Personnel » pour voir ses membres." />

            {/* Recherche : département, nom ou fonction (propositions sous la barre) */}
            <div ref={box} className="relative mb-6 max-w-md" onKeyDown={(event) => event.key === 'Escape' && setOpen(false)}>
                <label htmlFor="directory-search" className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                    Rechercher
                </label>
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <input
                        id="directory-search"
                        type="search"
                        placeholder="Département, nom ou fonction…"
                        autoComplete="off"
                        value={term}
                        onChange={(event) => {
                            setTerm(event.target.value);
                            setOpen(event.target.value.trim() !== '');
                        }}
                        onFocus={() => setOpen(searching)}
                        className="block w-full rounded-lg border border-slate-300 bg-white py-2.5 pr-3 pl-9 dark:border-slate-700 dark:bg-slate-900"
                    />
                </div>

                {open && (
                    <div className="absolute inset-x-0 z-30 mt-1 max-h-80 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                        {suggestions.map((person) => (
                            <button
                                key={person.id}
                                type="button"
                                className="block w-full px-3 py-2 text-left text-sm hover:bg-brand-50 dark:hover:bg-brand-900/40"
                                onClick={() => {
                                    setOpen(false);
                                    setExpanded((current) => ({ ...current, [person.departmentId]: true }));
                                    setFocusId(person.id);
                                }}
                            >
                                <span className="block font-medium">{person.name}</span>
                                <span className="block text-xs text-slate-500 dark:text-slate-400">{[person.jobTitle, person.department].filter(Boolean).join(' · ')}</span>
                            </button>
                        ))}
                        {suggestions.length === 0 && <p className="px-3 py-2.5 text-sm text-slate-500">Aucune personne pour « {term} ».</p>}
                    </div>
                )}
            </div>

            {/* Une carte par département */}
            <div className="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {departments.filter(departmentVisible).map((department) => {
                    const isOpen = Boolean(expanded[department.id]) || searching;
                    const members = searching ? visibleMembers(department) : department.members;

                    return (
                        <section key={department.id} className="card p-5" data-testid="department-card">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <h2 className="truncate font-semibold">{department.name}</h2>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        {department.members.length} membre(s) actif(s)
                                        {department.managerName && ` · Responsable : ${department.managerName}`}
                                    </p>
                                </div>
                                <span className="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                                    <Building2 className="size-5" aria-hidden="true" />
                                </span>
                            </div>

                            <div className="mt-4 flex items-end justify-between gap-3">
                                <a href={department.dossiersUrl} className="group block" title={`Voir les dossiers envoyés à ${department.name}`}>
                                    <div className="text-2xl font-bold tabular-nums group-hover:text-brand-600" data-testid="sent-count">
                                        {department.sent}
                                    </div>
                                    <div className="text-xs text-slate-500 group-hover:underline dark:text-slate-400">dossier(s) envoyé(s) à ce département</div>
                                </a>
                                <button
                                    type="button"
                                    className="btn-secondary px-3 py-1.5"
                                    aria-expanded={Boolean(expanded[department.id])}
                                    onClick={() => setExpanded((current) => ({ ...current, [department.id]: !current[department.id] }))}
                                >
                                    <Users className="size-4" aria-hidden="true" /> Personnel
                                </button>
                            </div>

                            {isOpen && (
                                <ul className="mt-4 space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                                    {members.map((member) => (
                                        <li key={member.id} className="flex items-center gap-3" data-person-id={member.id}>
                                            <UserAvatar photoUrl={member.photoUrl} initials={member.initials} />
                                            <div className="leading-tight">
                                                <div className="font-medium">
                                                    {member.name}
                                                    {member.isManager && (
                                                        <StatusBadge color="violet" className="ml-1">
                                                            Responsable
                                                        </StatusBadge>
                                                    )}
                                                </div>
                                                <div className="text-sm text-slate-500 dark:text-slate-400">{member.jobTitle}</div>
                                            </div>
                                        </li>
                                    ))}
                                    {department.members.length === 0 && <li className="text-sm text-slate-500">Aucun membre actif.</li>}
                                </ul>
                            )}
                        </section>
                    );
                })}
            </div>
        </>
    );
}
