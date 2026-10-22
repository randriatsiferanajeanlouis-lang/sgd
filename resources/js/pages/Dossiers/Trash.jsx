import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../components/Icon';
import PageHeader from '../../components/PageHeader';
import { paginationLabel, shortText } from '@/lib/text';

/**
 * Corbeille de l'expéditeur (React + Inertia). Les données viennent de
 * DossierTrashController@index ; « Restaurer » et « Supprimer définitivement »
 * repassent par les routes Laravel, qui vérifient les autorisations.
 */
export default function Trash({ title, dossiers, requestsUrl }) {
    const [busy, setBusy] = useState(null);

    const send = (dossier, method, url, question) => {
        if (!window.confirm(question)) {
            return;
        }

        router.visit(url, {
            method,
            preserveScroll: true,
            onStart: () => setBusy(dossier.id),
            onFinish: () => setBusy(null),
        });
    };

    return (
        <>
            <Head title={title} />

            <PageHeader
                title="Corbeille"
                kicker="Demande"
                subtitle="Les dossiers que vous avez supprimés. Ils sont retirés de votre espace uniquement : les destinataires les conservent, avec leur historique."
                actions={<a href={requestsUrl} className="btn-secondary">Retour aux demandes</a>}
            />

            <div className="card overflow-x-auto p-0">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Objet</th>
                            <th>Destinataires</th>
                            <th>Supprimé le</th>
                            <th className="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {dossiers.data.length === 0 && (
                            <tr>
                                <td colSpan="5" className="py-12 text-center text-slate-500">
                                    <span className="mx-auto mb-2 block w-fit text-slate-300 dark:text-slate-600"><Icon name="trash" className="size-8" /></span>
                                    La corbeille est vide.
                                </td>
                            </tr>
                        )}

                        {dossiers.data.map((dossier) => (
                            <tr key={dossier.id} className={busy === dossier.id ? 'opacity-50' : ''}>
                                <td data-label="Référence" className="font-mono text-xs font-semibold whitespace-nowrap">{dossier.reference}</td>
                                <td data-label="Objet">
                                    <div className="font-medium whitespace-nowrap" title={dossier.subject}>{shortText(dossier.subject)}</div>
                                    <div className="text-xs text-slate-500 dark:text-slate-400">{dossier.type}</div>
                                </td>
                                <td data-label="Destinataires" className="text-sm">{dossier.recipients}</td>
                                <td data-label="Supprimé le" className="whitespace-nowrap">{dossier.trashed_at}</td>
                                <td className="text-right">
                                    <div className="flex flex-wrap justify-end gap-2">
                                        <button
                                            type="button"
                                            className="btn-secondary px-3 py-1.5"
                                            disabled={busy !== null}
                                            onClick={() => send(dossier, 'post', dossier.restore_url, `Restaurer le dossier ${dossier.reference} dans vos demandes ?`)}
                                        >
                                            <Icon name="restore" className="size-4" /> Restaurer
                                        </button>
                                        <button
                                            type="button"
                                            className="btn-danger px-3 py-1.5"
                                            disabled={busy !== null}
                                            onClick={() => send(dossier, 'delete', dossier.destroy_url, `Supprimer définitivement le dossier ${dossier.reference} de votre espace ? Cette action est irréversible. Les destinataires le conservent.`)}
                                        >
                                            <Icon name="trash" className="size-4" /> Supprimer définitivement
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {dossiers.last_page > 1 && (
                <nav className="mt-4 flex flex-wrap gap-1" aria-label="Pagination">
                    {dossiers.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                className={link.active ? 'btn-primary px-3 py-1.5' : 'btn-secondary px-3 py-1.5'}
                            >
                                {paginationLabel(link.label)}
                            </Link>
                        ) : (
                            <span key={index} className="btn-secondary px-3 py-1.5 opacity-50">{paginationLabel(link.label)}</span>
                        ),
                    )}
                </nav>
            )}
        </>
    );
}
