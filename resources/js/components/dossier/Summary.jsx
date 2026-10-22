import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Calendar, Check, ChevronLeft, Clock, FileText, Inbox, Info, Mail, Pencil, Trash2, UserRound, Users } from 'lucide-react';
import ConfirmDialog from '@/components/app/ConfirmDialog';
import DeadlineBadge from '@/components/app/DeadlineBadge';
import StatusBadge from '@/components/app/StatusBadge';
import UserAvatar from '@/components/app/UserAvatar';
import { cn } from '@/lib/utils';

const CELL = 'flex items-start gap-3 bg-white p-4 dark:bg-slate-900';
const LABEL = 'text-[11px] font-bold tracking-[0.06em] text-slate-500 uppercase dark:text-slate-400';

/** Page précédente du site (sinon tableau de bord), comme le lien « Retour » de la fiche Blade. */
function previousPage(fallback) {
    try {
        const previous = new URL(document.referrer);
        if (previous.origin === window.location.origin && previous.pathname !== window.location.pathname) {
            return previous.href;
        }
    } catch {
        // Pas de page précédente.
    }

    return fallback;
}

/** En-tête de la fiche : fil d'Ariane, référence, statuts, actions, avancement et informations clés. */
export default function Summary({ dossier, can, urls }) {
    const [backUrl] = useState(() => previousPage(urls.dashboard));
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const percent = dossier.recipientCount ? Math.round((dossier.answeredCount / dossier.recipientCount) * 100) : 0;

    const destroy = () => {
        setDeleting(true);
        router.delete(urls.destroy, { onFinish: () => setDeleting(false) });
    };

    return (
        <>
            <nav className="mb-4 flex items-center gap-2 text-sm" aria-label="Fil d’Ariane">
                <a href={backUrl} className="inline-flex items-center gap-1.5 font-semibold text-brand-500 hover:underline dark:text-brand-300">
                    <ChevronLeft className="size-4" aria-hidden="true" />
                    Retour
                </a>
                <span className="text-slate-300 dark:text-slate-600">/</span>
                <span className="text-slate-500 dark:text-slate-400">Dossiers</span>
                <span className="text-slate-300 dark:text-slate-600">/</span>
                <span className="font-mono font-semibold text-univ-ink dark:text-white" aria-current="page">
                    {dossier.reference}
                </span>
            </nav>

            <div className="card overflow-hidden p-0">
                <div className="border-b border-univ-line bg-univ-surface/60 px-6 py-5 dark:border-slate-800 dark:bg-slate-950/30">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="rounded-md bg-brand-500 px-2 py-0.5 font-mono text-xs font-bold tracking-wide text-white dark:bg-brand-400">{dossier.reference}</span>
                                <StatusBadge color={dossier.status.color}>{dossier.status.label}</StatusBadge>
                                <StatusBadge color={dossier.external ? 'violet' : 'slate'}>{dossier.external ? 'Demande externe' : 'Demande interne'}</StatusBadge>
                                <DeadlineBadge deadline={dossier.deadline} />
                            </div>
                            <h1 className="mt-3 text-xl leading-snug font-extrabold tracking-[-0.02em] break-words text-univ-ink sm:text-2xl dark:text-white">{dossier.subject}</h1>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {can.viewHistory && (
                                <a href="#historique" className="btn-secondary">
                                    <Clock className="size-4" aria-hidden="true" /> Historique
                                </a>
                            )}
                            {can.update && (
                                <a href={urls.edit} className="btn-secondary">
                                    <Pencil className="size-4" aria-hidden="true" /> Modifier
                                </a>
                            )}
                            {can.delete && (
                                <button type="button" className="btn-danger" onClick={() => setConfirmDelete(true)}>
                                    <Trash2 className="size-4" aria-hidden="true" /> Supprimer
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Avancement du dossier */}
                    <ol className="mt-5 flex items-center" aria-label="Avancement du dossier">
                        {dossier.steps.map((step, index) => {
                            const done = index <= dossier.currentStep;
                            const last = index === dossier.steps.length - 1;

                            return (
                                <li key={step} className={cn('flex items-center', !last && 'flex-1')} aria-current={index === dossier.currentStep ? 'step' : undefined}>
                                    <span className="flex items-center gap-2">
                                        <span
                                            className={cn(
                                                'flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                                                done ? 'bg-brand-500 text-white dark:bg-brand-400' : 'border border-univ-line bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-900',
                                                index === dossier.currentStep && 'ring-4 ring-brand-500/15',
                                            )}
                                        >
                                            {index < dossier.currentStep ? <Check className="size-3.5" strokeWidth={3} aria-hidden="true" /> : index + 1}
                                        </span>
                                        <span className={cn('hidden text-xs sm:inline', done ? 'font-bold text-univ-ink dark:text-white' : 'text-slate-400')}>{step}</span>
                                    </span>
                                    {!last && (
                                        <span className={cn('mx-3 h-0.5 flex-1 rounded-full', index < dossier.currentStep ? 'bg-brand-500 dark:bg-brand-400' : 'bg-univ-line dark:bg-slate-700')} />
                                    )}
                                </li>
                            );
                        })}
                    </ol>
                </div>

                <div className="px-6 py-5">
                    {dossier.deadline?.overdue && (
                        <div className="mb-5 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert">
                            <Clock className="size-5 shrink-0" aria-hidden="true" />
                            <span>Statut : {dossier.status.label} — Alerte : délai dépassé.</span>
                        </div>
                    )}

                    <dl className="grid gap-px overflow-hidden rounded-lg border border-univ-line bg-univ-line text-sm sm:grid-cols-2 lg:grid-cols-3 dark:border-slate-800 dark:bg-slate-800">
                        <div className={CELL}>
                            <FileText className="mt-0.5 size-5 shrink-0 text-brand-400" aria-hidden="true" />
                            <div className="min-w-0">
                                <dt className={LABEL}>Type de demande</dt>
                                <dd className="mt-1 font-semibold break-words">{dossier.type}</dd>
                            </div>
                        </div>
                        <div className={CELL}>
                            <Info className="mt-0.5 size-5 shrink-0 text-brand-400" aria-hidden="true" />
                            <div>
                                <dt className={LABEL}>Urgence</dt>
                                <dd className="mt-1 flex items-center gap-2 font-semibold">
                                    {dossier.urgency && <span className="size-2.5 rounded-full" style={{ backgroundColor: dossier.urgency.color }} aria-hidden="true" />}
                                    {dossier.urgency?.name ?? 'Non précisée'}
                                </dd>
                            </div>
                        </div>
                        <div className={CELL}>
                            <UserAvatar photoUrl={dossier.creator.photoUrl} initials={dossier.creator.initials} size="size-9" className="shrink-0 text-xs" />
                            <div className="min-w-0">
                                <dt className={LABEL}>{dossier.external ? 'Enregistré par' : 'Expéditeur'}</dt>
                                <dd className="mt-1 font-semibold">{dossier.creator.name}</dd>
                                <dd className="text-xs text-slate-500 dark:text-slate-400">{dossier.creator.detail}</dd>
                            </div>
                        </div>
                        <div className={CELL}>
                            <Calendar className="mt-0.5 size-5 shrink-0 text-brand-400" aria-hidden="true" />
                            <div>
                                <dt className={LABEL}>Créé le</dt>
                                <dd className="mt-1 font-semibold">
                                    {dossier.createdDate} <span className="font-normal text-slate-500 dark:text-slate-400">à {dossier.createdTime}</span>
                                </dd>
                            </div>
                        </div>
                        <div className={CELL}>
                            <Clock className="mt-0.5 size-5 shrink-0 text-brand-400" aria-hidden="true" />
                            <div>
                                <dt className={LABEL}>Échéance</dt>
                                <dd className="mt-1 font-semibold">
                                    {dossier.dueDate ? (
                                        <>
                                            {dossier.dueDate} <span className="font-normal text-slate-500 dark:text-slate-400">à {dossier.dueTime}</span>
                                        </>
                                    ) : (
                                        '—'
                                    )}
                                </dd>
                            </div>
                        </div>
                        <div className={CELL}>
                            <Users className="mt-0.5 size-5 shrink-0 text-brand-400" aria-hidden="true" />
                            <div className="min-w-0 flex-1">
                                <dt className={LABEL}>Réponses reçues</dt>
                                <dd className="mt-1 font-semibold">
                                    {dossier.answeredCount} / {dossier.recipientCount}{' '}
                                    <span className="font-normal text-slate-500 dark:text-slate-400">destinataire{dossier.recipientCount > 1 ? 's' : ''}</span>
                                </dd>
                                <dd className="mt-2 h-1.5 overflow-hidden rounded-full bg-univ-line dark:bg-slate-700" aria-hidden="true">
                                    <div className="h-full rounded-full bg-green-500" style={{ width: `${percent}%` }} />
                                </dd>
                            </div>
                        </div>
                        {dossier.external && (
                            <>
                                <div className={CELL}>
                                    <Inbox className="mt-0.5 size-5 shrink-0 text-brand-400" aria-hidden="true" />
                                    <div>
                                        <dt className={LABEL}>Reçu le</dt>
                                        <dd className="mt-1 font-semibold">{dossier.receivedAt ?? '—'}</dd>
                                    </div>
                                </div>
                                <div className={CELL}>
                                    <Mail className="mt-0.5 size-5 shrink-0 text-brand-400" aria-hidden="true" />
                                    <div>
                                        <dt className={LABEL}>Canal de réception</dt>
                                        <dd className="mt-1 font-semibold">{dossier.receptionChannel ?? '—'}</dd>
                                    </div>
                                </div>
                            </>
                        )}
                    </dl>

                    {dossier.requester && (
                        <div className="mt-5 flex items-start gap-3 rounded-lg border border-violet-200 bg-violet-50/60 p-4 text-sm dark:border-violet-900 dark:bg-violet-950/30">
                            <UserRound className="mt-0.5 size-5 shrink-0 text-violet-600 dark:text-violet-300" aria-hidden="true" />
                            <div>
                                <div className="text-[11px] font-bold tracking-[0.06em] text-violet-700 uppercase dark:text-violet-300">Demandeur externe</div>
                                <div className="mt-1 font-semibold">
                                    {dossier.requester.name}
                                    {dossier.requester.organization && <span className="font-normal"> — {dossier.requester.organization}</span>}
                                </div>
                                <div className="text-slate-500 dark:text-slate-400">{dossier.requester.contact}</div>
                            </div>
                        </div>
                    )}

                    {dossier.description && (
                        <div className="mt-5">
                            <div className={LABEL}>Description</div>
                            <div className="mt-2 rounded-lg border-l-4 border-brand-500 bg-univ-surface/60 px-4 py-3 leading-relaxed whitespace-pre-line dark:border-brand-400 dark:bg-slate-950/30">
                                {dossier.description}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                danger
                Icon={Trash2}
                title="Supprimer le dossier"
                question={`Placer le dossier ${dossier.reference} dans la corbeille ? Il disparaîtra de votre espace, mais les destinataires le conserveront. Vous pourrez le restaurer depuis la corbeille.`}
                confirmLabel="Placer dans la corbeille"
                onConfirm={destroy}
                processing={deleting}
            />
        </>
    );
}
