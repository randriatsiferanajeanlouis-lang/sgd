import { Head } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import DeadlineBadge from '@/components/app/DeadlineBadge';
import PageHeader from '@/components/app/PageHeader';
import Pagination from '@/components/app/Pagination';
import StatusBadge from '@/components/app/StatusBadge';
import { shortText } from '@/lib/text';

/** Bouton « Voir » d'un dossier (même apparence que <x-view-button>). */
function ViewButton({ dossier }) {
    return (
        <a href={dossier.url} className="btn-secondary min-h-8 px-3 py-1.5 text-xs" aria-label={`Voir le dossier ${dossier.reference}`}>
            <Eye className="size-4" aria-hidden="true" /> Voir
        </a>
    );
}

/**
 * Validations (React), pour les responsables (permission validate_dossier) :
 * demandes transmises par leurs employés en attente de décision, puis
 * décisions déjà prises. La décision se prend sur la fiche du dossier.
 */
export default function Index({ pending, decided }) {
    return (
        <>
            <Head title="Validations" />

            <PageHeader title="Validations" subtitle="Dossiers transmis par vos employés pour validation." />

            <section className="card overflow-x-auto p-0">
                <h2 className="px-6 pt-5 text-lg font-semibold">À valider</h2>
                <table className="data-table mt-3">
                    <thead>
                        <tr>
                            <th>Dossier</th>
                            <th>Employé</th>
                            <th>Commentaire</th>
                            <th>Urgence</th>
                            <th>Échéance</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {pending.map((validation) => (
                            <tr key={validation.id}>
                                <td data-label="Dossier" className="min-w-[15rem]">
                                    <div className="font-mono text-xs font-semibold whitespace-nowrap">{validation.dossier.reference}</div>
                                    <div className="whitespace-nowrap" title={validation.dossier.subject}>
                                        {shortText(validation.dossier.subject)}
                                    </div>
                                </td>
                                <td data-label="Employé">{validation.requester}</td>
                                <td data-label="Commentaire" className="max-w-xs text-sm">
                                    {validation.comment || '—'}
                                </td>
                                <td data-label="Urgence">{validation.urgency ?? '—'}</td>
                                <td data-label="Échéance">
                                    <DeadlineBadge deadline={validation.deadline} />
                                </td>
                                <td className="text-right whitespace-nowrap">
                                    <ViewButton dossier={validation.dossier} />
                                </td>
                            </tr>
                        ))}
                        {pending.length === 0 && (
                            <tr>
                                <td colSpan={6} className="py-8 text-center text-slate-500">
                                    Aucune demande de validation en attente.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </section>

            <section className="card mt-6 overflow-x-auto p-0">
                <h2 className="px-6 pt-5 text-lg font-semibold">Décisions prises</h2>
                <table className="data-table mt-3">
                    <thead>
                        <tr>
                            <th>Dossier</th>
                            <th>Employé</th>
                            <th>Décision</th>
                            <th>Date</th>
                            <th className="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {decided.data.map((validation) => (
                            <tr key={validation.id}>
                                <td data-label="Dossier">
                                    <a href={validation.dossier.url} className="font-mono text-xs font-semibold whitespace-nowrap text-brand-600 hover:underline dark:text-brand-300">
                                        {validation.dossier.reference}
                                    </a>
                                    <div className="text-xs text-slate-500 dark:text-slate-400" title={validation.dossier.subject}>
                                        {shortText(validation.dossier.subject)}
                                    </div>
                                </td>
                                <td data-label="Employé">{validation.requester}</td>
                                <td data-label="Décision">
                                    <StatusBadge color={validation.status.color}>{validation.status.label}</StatusBadge>
                                </td>
                                <td data-label="Date">{validation.decidedAt}</td>
                                <td className="text-right whitespace-nowrap">
                                    <ViewButton dossier={validation.dossier} />
                                </td>
                            </tr>
                        ))}
                        {decided.data.length === 0 && (
                            <tr>
                                <td colSpan={5} className="py-8 text-center text-slate-500">
                                    Aucune décision.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </section>

            <div className="mt-4">
                <Pagination paginator={decided} />
            </div>
        </>
    );
}
