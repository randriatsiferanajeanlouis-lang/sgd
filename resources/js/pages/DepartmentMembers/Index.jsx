import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { BarChart3, Clock, UserX, Users } from 'lucide-react';
import ConfirmDialog from '@/components/app/ConfirmDialog';
import Pagination from '@/components/app/Pagination';
import UserAvatar from '@/components/app/UserAvatar';
import { cn } from '@/lib/utils';

const TABS = [
    ['all', 'Tous'],
    ['active', 'Actifs'],
    ['inactive', 'Désactivés'],
];

/** Petite barre de progression (pourcentage). */
const Bar = ({ value, className = 'w-24' }) => (
    <span className={cn('h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700', className)}>
        <span className="block h-full rounded-full bg-brand-500" style={{ width: `${value ?? 0}%` }} />
    </span>
);

/** Une ligne de l'équipe : charge de traitement, état, désactivation du compte si autorisée. */
function Member({ member }) {
    const [open, setOpen] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const form = useForm({ comment: '' });

    const [dot, state, pill] = !member.active
        ? ['border border-slate-400 bg-transparent', 'Désactivé', 'text-slate-500 dark:text-slate-400']
        : member.overdue > 0
          ? ['bg-red-500', 'En retard', 'text-slate-700 dark:text-slate-200']
          : member.open > 0
            ? ['bg-brand-500', 'En cours', 'text-slate-700 dark:text-slate-200']
            : ['bg-emerald-500', 'À jour', 'text-slate-700 dark:text-slate-200'];

    const deactivate = () =>
        form.post(member.deactivateUrl, {
            preserveScroll: true,
            onFinish: () => setConfirming(false),
            onSuccess: () => setOpen(false),
        });

    return (
        <li className={cn('rounded-lg px-3 py-3.5', member.active && member.overdue > 0 && 'bg-red-50 dark:bg-red-500/10')} data-member-id={member.id}>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                <UserAvatar photoUrl={member.photoUrl} initials={member.initials} size="size-9" className={member.active ? '' : 'opacity-50'} />

                <div className="min-w-0 flex-1">
                    <div className="truncate font-medium">{member.displayName}</div>
                    <div className="truncate text-xs text-slate-500 dark:text-slate-400">
                        {member.jobTitle || '—'} · {member.role}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3 text-sm">
                    {member.overdue > 0 && <span className="font-medium text-red-600 dark:text-red-400">{member.overdue} en retard</span>}
                    {member.open > 0 && <span className="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-700 tabular-nums dark:bg-slate-800 dark:text-slate-300">{member.open} en cours</span>}
                    <span className="hidden text-slate-500 sm:inline dark:text-slate-400">{member.answered} répondu(s)</span>
                    <span className={cn('inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium dark:bg-slate-800', pill)} data-label="Statut du compte">
                        <span className={cn('size-1.5 rounded-full', dot)} aria-hidden="true" />
                        {state}
                    </span>

                    {member.canDeactivate && (
                        <button
                            type="button"
                            onClick={() => setOpen(!open)}
                            aria-expanded={open}
                            className="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                        >
                            Désactiver
                        </button>
                    )}
                </div>
            </div>

            {member.canDeactivate && open && (
                <form
                    className="mt-3 flex flex-wrap items-center gap-2 pl-13"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setConfirming(true);
                    }}
                >
                    <input
                        type="text"
                        name="comment"
                        placeholder="Motif (facultatif)"
                        aria-label={`Motif de la désactivation de ${member.name}`}
                        value={form.data.comment}
                        onChange={(event) => form.setData('comment', event.target.value)}
                        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm sm:w-64 dark:border-slate-700 dark:bg-slate-900"
                    />
                    <button type="submit" className="btn-danger px-3 py-1.5">
                        Confirmer la désactivation
                    </button>
                    <button type="button" onClick={() => setOpen(false)} className="text-sm text-slate-500 hover:text-slate-800 dark:hover:text-white">
                        Annuler
                    </button>
                    {form.errors.comment && <p className="w-full text-sm text-red-600 dark:text-red-400">{form.errors.comment}</p>}
                </form>
            )}

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                danger
                Icon={UserX}
                title="Désactiver le compte"
                question={`Désactiver le compte de ${member.name} ? L’administrateur sera notifié.`}
                confirmLabel="Désactiver"
                onConfirm={deactivate}
                processing={form.processing}
            />
        </li>
    );
}

/**
 * Employés du département (React), espace Responsable : charge de traitement
 * de l'équipe et indicateurs de suivi, sans données personnelles inutiles.
 * Droits côté Laravel (viewDepartmentMembers, deactivate).
 */
export default function Index({ department, managerName, summary, nextDue, attention, members, urls }) {
    const [tab, setTab] = useState('all');
    const inactive = summary.members - summary.active;
    const activeRate = summary.members > 0 ? Math.round((summary.active / summary.members) * 100) : 0;
    const counts = { all: summary.members, active: summary.active, inactive };
    const list = members.data.filter((member) => tab === 'all' || (tab === 'active' ? member.active : !member.active));

    return (
        <>
            <Head title="Employés" />

            {/* Fil d'Ariane */}
            <nav className="mb-4 flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400" aria-label="Fil d’Ariane">
                <a href={urls.dashboard} className="hover:text-slate-800 dark:hover:text-white">
                    Espace responsable
                </a>
                <span aria-hidden="true">›</span>
                <span className="font-medium text-slate-800 dark:text-white">Employés</span>
            </nav>

            {/* En-tête du département */}
            <header className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex min-w-0 items-center gap-4">
                    <span className="inline-flex size-14 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-brand-400 to-violet-500 text-lg font-bold text-white shadow-lg shadow-brand-500/30" aria-hidden="true">
                        {department.initials}
                    </span>
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold">{department.name}</h1>
                            {summary.overdue > 0 ? (
                                <span className="inline-flex items-center gap-1.5 rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                    <span className="size-1.5 rounded-full bg-amber-500" aria-hidden="true" />
                                    {summary.overdue} en retard
                                </span>
                            ) : (
                                <span className="inline-flex items-center gap-1.5 rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                                    <span className="size-1.5 rounded-full bg-emerald-500" aria-hidden="true" />À jour
                                </span>
                            )}
                        </div>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Employés du département · {summary.members} membre(s)
                            {managerName && ` · Responsable : ${managerName}`}
                            {department.code && ` · ${department.code}`}
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {urls.statistics && (
                        <a
                            href={urls.statistics}
                            className="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                        >
                            <BarChart3 className="size-4" aria-hidden="true" /> Statistiques
                        </a>
                    )}
                </div>
            </header>

            {/* Bandeau d'indicateurs */}
            <dl className="mt-6 grid overflow-hidden rounded-xl border border-slate-200 bg-white sm:grid-cols-2 lg:grid-cols-4 dark:border-slate-800 dark:bg-slate-900">
                <div className="border-b border-slate-200 px-5 py-4 sm:border-r lg:border-b-0 dark:border-slate-800">
                    <dt className="text-xs text-slate-500 dark:text-slate-400">Membres</dt>
                    <dd className="mt-1.5 flex items-center gap-3">
                        <span className="font-semibold">{summary.active} actif(s)</span>
                        <Bar value={activeRate} />
                    </dd>
                </div>
                <div className="border-b border-slate-200 px-5 py-4 lg:border-r lg:border-b-0 dark:border-slate-800">
                    <dt className="text-xs text-slate-500 dark:text-slate-400">Traitements ouverts</dt>
                    <dd className="mt-1.5 flex items-center gap-2">
                        <span className="font-semibold">{summary.open} en cours</span>
                        {summary.overdue > 0 && <span className="rounded-md bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-500/15 dark:text-red-300">{summary.overdue} en retard</span>}
                    </dd>
                </div>
                <div className="border-b border-slate-200 px-5 py-4 sm:border-r sm:border-b-0 dark:border-slate-800">
                    <dt className="text-xs text-slate-500 dark:text-slate-400">Taux de réponse</dt>
                    <dd className="mt-1.5 flex items-center gap-3">
                        <span className="font-semibold">{summary.answerRate !== null ? `${summary.answerRate} %` : '—'}</span>
                        <Bar value={summary.answerRate} />
                    </dd>
                </div>
                <div className="px-5 py-4">
                    <dt className="text-xs text-slate-500 dark:text-slate-400">Prochaine échéance</dt>
                    <dd className="mt-1.5 font-semibold">{nextDue?.short ?? '—'}</dd>
                </div>
            </dl>

            {/* Onglets */}
            <div className="mt-6 flex gap-6 border-b border-slate-200 text-sm dark:border-slate-800" role="tablist">
                {TABS.map(([key, label]) => (
                    <button
                        key={key}
                        type="button"
                        role="tab"
                        aria-selected={tab === key}
                        onClick={() => setTab(key)}
                        className={cn(
                            '-mb-px flex items-center gap-2 border-b-2 pb-3 font-medium transition',
                            tab === key ? 'border-brand-500 text-slate-900 dark:text-white' : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white',
                        )}
                    >
                        {label}
                        <span className="inline-flex min-w-5 items-center justify-center rounded-full bg-slate-100 px-1.5 text-xs text-slate-600 tabular-nums dark:bg-slate-800 dark:text-slate-300">{counts[key]}</span>
                    </button>
                ))}
            </div>

            <div className="mt-6 grid items-start gap-6 lg:grid-cols-3">
                {/* Liste de l'équipe */}
                <section className="card p-0 lg:col-span-2">
                    <div className="flex flex-wrap items-center gap-2 px-5 pt-5 pb-3">
                        <Users className="size-5 text-slate-500 dark:text-slate-400" aria-hidden="true" />
                        <h2 className="font-semibold">Équipe</h2>
                        <span className="text-sm text-slate-500 dark:text-slate-400">
                            {summary.active} actif(s) · {inactive} désactivé(s)
                        </span>
                    </div>

                    <ul className="divide-y divide-slate-100 px-2 pb-2 dark:divide-slate-800">
                        {list.map((member) => (
                            <Member key={member.id} member={member} />
                        ))}
                        {members.data.length === 0 && <li className="px-3 py-8 text-center text-slate-500">Aucun employé dans ce département.</li>}
                    </ul>
                </section>

                {/* Colonne de droite */}
                <div className="space-y-6">
                    {/* Point d'attention */}
                    <section className="relative overflow-hidden rounded-xl border border-brand-300/40 bg-linear-to-br from-white via-brand-50 to-violet-100 p-5 dark:border-brand-500/30 dark:from-slate-900 dark:via-brand-900/40 dark:to-violet-900/50">
                        <span className="inline-flex items-center gap-1.5 rounded-md bg-brand-500/10 px-2 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">✦ Point d’attention</span>
                        {attention ? (
                            <>
                                <h2 className="mt-3 font-semibold">
                                    {attention.name} a {attention.overdue} traitement(s) en retard
                                </h2>
                                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">Sur {attention.open} dossier(s) en cours. Un rappel ou une nouvelle répartition peut l’aider à rattraper son retard.</p>
                            </>
                        ) : (
                            <>
                                <h2 className="mt-3 font-semibold">Aucun retard dans l’équipe</h2>
                                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">Tous les traitements ouverts sont dans les délais.</p>
                            </>
                        )}
                    </section>

                    {/* Prochaine échéance */}
                    <section className="card p-5">
                        <div className="flex items-center gap-2">
                            <Clock className="size-5 text-slate-500 dark:text-slate-400" aria-hidden="true" />
                            <h2 className="font-semibold">Prochaine échéance</h2>
                            {summary.open > 0 && (
                                <span className="ml-auto inline-flex items-center gap-1.5 rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                    <span className="size-1.5 rounded-full bg-amber-500" aria-hidden="true" />
                                    Ouvert
                                </span>
                            )}
                        </div>
                        <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">
                            {nextDue ? (
                                <>
                                    Le prochain traitement à rendre est attendu le <strong>{nextDue.long}</strong>.
                                </>
                            ) : (
                                'Aucun traitement ouvert à venir.'
                            )}
                        </p>
                        <p className="mt-3 rounded-lg border border-brand-300/40 bg-brand-50 px-3 py-2 text-sm text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">
                            {summary.open} traitement(s) ouvert(s) dans le département, dont {summary.overdue} en retard.
                        </p>
                    </section>

                    {/* Suivi de l'équipe */}
                    <section className="card p-5">
                        <div className="flex items-center gap-2">
                            <BarChart3 className="size-5 text-slate-500 dark:text-slate-400" aria-hidden="true" />
                            <h2 className="font-semibold">Suivi de l’équipe</h2>
                        </div>
                        <dl className="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                            <div className="flex items-center justify-between gap-3 py-3">
                                <dt className="text-slate-600 dark:text-slate-300">Taux de réponse</dt>
                                <dd className="flex items-center gap-3">
                                    <Bar value={summary.answerRate} className="w-20" />
                                    <span className="w-10 text-right font-semibold tabular-nums">{summary.answerRate !== null ? `${summary.answerRate}%` : '—'}</span>
                                </dd>
                            </div>
                            <div className="flex items-center justify-between gap-3 py-3">
                                <dt className="text-slate-600 dark:text-slate-300">Comptes actifs</dt>
                                <dd className="flex items-center gap-3">
                                    <Bar value={activeRate} className="w-20" />
                                    <span className="w-10 text-right font-semibold tabular-nums">{activeRate}%</span>
                                </dd>
                            </div>
                            <div className="flex items-center justify-between gap-3 py-3">
                                <dt className="text-slate-600 dark:text-slate-300">Délai moyen de réponse</dt>
                                <dd className="font-semibold tabular-nums">{summary.averageHours !== null ? `${summary.averageHours} h` : '—'}</dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </div>

            <div className="mt-4">
                <Pagination paginator={members} />
            </div>
        </>
    );
}
