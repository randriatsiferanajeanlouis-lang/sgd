import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Users } from 'lucide-react';
import DeadlineBadge from '@/components/app/DeadlineBadge';
import SectionTitle from '@/components/app/SectionTitle';
import StatusBadge from '@/components/app/StatusBadge';
import UserAvatar from '@/components/app/UserAvatar';

/** Réouvrir un traitement : le motif est demandé avant de confirmer. */
function Reopen({ recipient }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ comment: '' });

    if (!open) {
        return (
            <button type="button" className="btn-secondary px-3 py-1.5" onClick={() => setOpen(true)}>
                Réouvrir
            </button>
        );
    }

    return (
        <form
            className="flex flex-col items-end gap-1"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(recipient.reopenUrl, { preserveScroll: true });
            }}
        >
            <div className="flex gap-1">
                <input
                    type="text"
                    name="comment"
                    required
                    autoFocus
                    placeholder="Motif"
                    aria-label="Motif de la réouverture"
                    value={form.data.comment}
                    onChange={(event) => form.setData('comment', event.target.value)}
                    className="w-40 rounded-lg border border-slate-300 bg-white px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900"
                />
                <button type="submit" className="btn-primary px-3 py-1.5" disabled={form.processing}>
                    Confirmer
                </button>
            </div>
            {form.errors.comment && <p className="text-xs text-red-600 dark:text-red-400">{form.errors.comment}</p>}
        </form>
    );
}

/** Tableau des destinataires : statut, réponse, échéance, Relancer / Réouvrir selon les droits. */
export default function Recipients({ recipients }) {
    const [reminding, setReminding] = useState(null);

    return (
        <section className="card overflow-x-auto p-0" aria-labelledby="recipients-title">
            <div className="px-6 pt-5">
                <SectionTitle id="recipients-title" Icon={Users} count={recipients.length}>
                    Destinataires
                </SectionTitle>
            </div>
            <table className="data-table mt-4">
                <thead>
                    <tr>
                        <th>Destinataire</th>
                        <th>Statut</th>
                        <th>Réponse</th>
                        <th>Échéance</th>
                        <th className="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {recipients.map((recipient) => (
                        <tr key={recipient.id}>
                            <td data-label="Destinataire">
                                <div className="flex items-center gap-3">
                                    <UserAvatar photoUrl={recipient.person.photoUrl} initials={recipient.person.initials} size="size-8" className="shrink-0 text-xs" />
                                    <div className="min-w-0">
                                        <div className="font-semibold whitespace-nowrap">{recipient.person.name}</div>
                                        <div className="text-xs text-slate-500 dark:text-slate-400">{recipient.person.detail}</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Statut">
                                <StatusBadge color={recipient.status.color}>{recipient.status.label}</StatusBadge>
                            </td>
                            <td data-label="Réponse" className="text-xs text-slate-500 dark:text-slate-400">
                                {recipient.answeredDate ? (
                                    <>
                                        <span className="font-medium text-slate-700 dark:text-slate-200">{recipient.answeredDate}</span> à {recipient.answeredTime}
                                    </>
                                ) : (
                                    'En attente'
                                )}
                                {recipient.archivedDate && <div>Archivé le {recipient.archivedDate}</div>}
                            </td>
                            <td data-label="Échéance">
                                <DeadlineBadge deadline={recipient.deadline} />
                            </td>
                            <td className="text-right">
                                <div className="flex flex-wrap justify-end gap-2">
                                    {recipient.canRemind && (
                                        <button
                                            type="button"
                                            className="btn-secondary px-3 py-1.5"
                                            disabled={reminding === recipient.id}
                                            onClick={() => {
                                                setReminding(recipient.id);
                                                router.post(recipient.remindUrl, {}, { preserveScroll: true, onFinish: () => setReminding(null) });
                                            }}
                                        >
                                            Relancer
                                        </button>
                                    )}
                                    {recipient.canReopen && <Reopen recipient={recipient} />}
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </section>
    );
}
