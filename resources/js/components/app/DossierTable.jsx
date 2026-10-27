import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Eye, Pencil, Trash2 } from 'lucide-react';
import ConfirmDialog from '@/components/app/ConfirmDialog';
import Pagination from '@/components/app/Pagination';
import StatusBadge from '@/components/app/StatusBadge';
import { shortText } from '@/lib/text';

/**
 * Tableau de dossiers (équivalent React du composant Blade <x-dossier-table>).
 * Chaque ligne vient de App\Support\DossierListItem : statut « mon traitement »,
 * échéance et droit de modification sont déjà calculés pour l'utilisateur.
 */
export default function DossierTable({ dossiers, actions = true }) {
    const rows = dossiers.data ?? [];
    // « Supprimer » : mise à la corbeille de l'expéditeur, après confirmation.
    const [pendingDelete, setPendingDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const confirmDelete = () => {
        setDeleting(true);
        router.delete(pendingDelete.deleteUrl, {
            preserveScroll: true,
            onFinish: () => setDeleting(false),
            onSuccess: () => setPendingDelete(null),
        });
    };

    return (
        <>
            <div className="card list-scroll p-0">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Objet</th>
                            <th>Expéditeur</th>
                            <th>Destinataires</th>
                            <th>Statut</th>
                            <th>Échéance</th>
                            {actions && <th className="text-right">Actions</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((dossier) => (
                            <tr key={dossier.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td data-label="Référence" className="font-mono text-xs whitespace-nowrap">
                                    <a href={dossier.showUrl} className="font-semibold text-brand-600 hover:underline dark:text-brand-300">
                                        {dossier.reference}
                                    </a>
                                    {dossier.external && (
                                        <StatusBadge color="violet" className="ml-1">
                                            Externe
                                        </StatusBadge>
                                    )}
                                </td>
                                <td data-label="Objet">
                                    <a href={dossier.showUrl} className="font-medium whitespace-nowrap hover:underline" title={dossier.subject}>
                                        {shortText(dossier.subject)}
                                    </a>
                                    <div className="text-xs text-slate-500 dark:text-slate-400">{dossier.type}</div>
                                </td>
                                <td data-label="Expéditeur" className="whitespace-nowrap">
                                    {dossier.sender}
                                </td>
                                <td data-label="Destinataires">
                                    <div className="flex flex-wrap gap-1">
                                        {dossier.recipients.map((recipient, index) => (
                                            <StatusBadge key={index} color={recipient.color} title={recipient.status}>
                                                {recipient.name} · {recipient.status}
                                            </StatusBadge>
                                        ))}
                                    </div>
                                </td>
                                <td data-label="Statut">
                                    <StatusBadge color={dossier.status.color}>{dossier.status.label}</StatusBadge>
                                    {dossier.status.mine && <div className="mt-0.5 text-xs text-slate-500">mon traitement</div>}
                                    {dossier.removal && (
                                        <div className="mt-1">
                                            <StatusBadge color={dossier.removal.state === 'purged' ? 'red' : 'amber'}>{dossier.removal.label}</StatusBadge>
                                            <div className="text-xs text-slate-500 dark:text-slate-400">le {dossier.removal.at}</div>
                                        </div>
                                    )}
                                </td>
                                <td data-label="Échéance" className="whitespace-nowrap">
                                    {dossier.deadline &&
                                        (dossier.deadline.color ? (
                                            <StatusBadge color={dossier.deadline.color}>
                                                {dossier.deadline.overdue && '⚠ '}
                                                {dossier.deadline.label}
                                            </StatusBadge>
                                        ) : (
                                            <span className="text-xs text-slate-500 dark:text-slate-400">{dossier.deadline.label}</span>
                                        ))}
                                </td>
                                {actions && (
                                    <td className="text-right whitespace-nowrap">
                                        <div className="inline-flex justify-end gap-2">
                                            <a href={dossier.showUrl} className="btn-secondary min-h-8 px-3 py-1.5 text-xs" aria-label={`Voir le dossier ${dossier.reference}`}>
                                                <Eye className="size-4" aria-hidden="true" /> Voir
                                            </a>
                                            {dossier.editUrl && (
                                                <a href={dossier.editUrl} className="btn-secondary min-h-8 px-2.5 py-1.5" title="Modifier" aria-label={`Modifier ${dossier.reference}`}>
                                                    <Pencil className="size-4" aria-hidden="true" />
                                                </a>
                                            )}
                                            {dossier.deleteUrl && (
                                                <button type="button" className="btn-secondary min-h-8 px-2.5 py-1.5 text-red-600" title="Placer dans la corbeille" aria-label={`Supprimer ${dossier.reference}`} onClick={() => setPendingDelete(dossier)}>
                                                    <Trash2 className="size-4" aria-hidden="true" />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                )}
                            </tr>
                        ))}

                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={actions ? 7 : 6} className="py-10 text-center text-slate-500">
                                    Aucun dossier.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="mt-4">
                <Pagination paginator={dossiers} />
            </div>

            <ConfirmDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => !open && setPendingDelete(null)}
                danger
                Icon={Trash2}
                title="Supprimer le dossier"
                question={`Placer le dossier ${pendingDelete?.reference} dans la corbeille ? Il disparaîtra de votre espace, mais les destinataires le conserveront. Vous pourrez le restaurer depuis la corbeille.`}
                confirmLabel="Placer dans la corbeille"
                onConfirm={confirmDelete}
                processing={deleting}
            />
        </>
    );
}
