import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { RotateCcw, Trash2 } from 'lucide-react';
import ConfirmDialog from '@/components/app/ConfirmDialog';
import Icon from '../../components/Icon';
import PageHeader from '../../components/PageHeader';
import { paginationLabel, shortText } from '@/lib/text';

/**
 * Corbeille de l'expéditeur (React + Inertia). Les données viennent de
 * DossierTrashController@index ; « Restaurer » et « Supprimer définitivement »
 * repassent par les routes Laravel, qui vérifient les autorisations.
 */
export default function Trash({ title, dossiers, requestsUrl, bulkUrls }) {
    const [busy, setBusy] = useState(null);
    // Confirmation en attente : { kind: 'restore' | 'destroy', items: dossiers }.
    const [pending, setPending] = useState(null);
    const [selected, setSelected] = useState([]);
    const rows = dossiers.data;
    const allChecked = rows.length > 0 && rows.every((row) => selected.includes(row.id));

    // La liste est rechargée par le serveur après chaque opération : on ne garde que les lignes encore affichées.
    useEffect(() => setSelected((ids) => ids.filter((id) => rows.some((row) => row.id === id))), [rows]);

    const toggle = (id, checked) => setSelected((ids) => (checked ? [...ids, id] : ids.filter((item) => item !== id)));

    const confirm = () => {
        const { kind, items } = pending;
        const options = {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(null),
            onSuccess: () => {
                setPending(null);
                setSelected([]);
            },
        };

        if (items.length === 1) {
            router.visit(kind === 'restore' ? items[0].restore_url : items[0].destroy_url, { method: kind === 'restore' ? 'post' : 'delete', ...options });
        } else {
            router.visit(bulkUrls[kind], { method: kind === 'restore' ? 'post' : 'delete', data: { ids: items.map((item) => item.id) }, ...options });
        }
    };

    const many = pending?.items.length > 1;
    const wording = {
        restore: {
            title: many ? `Restaurer ${pending?.items.length} dossiers` : 'Restaurer le dossier',
            question: many ? `Restaurer ces ${pending?.items.length} dossiers dans vos demandes ?` : `Restaurer le dossier ${pending?.items[0].reference} dans vos demandes ?`,
            label: 'Restaurer',
        },
        destroy: {
            title: many ? `Supprimer définitivement ${pending?.items.length} dossiers` : 'Supprimer définitivement le dossier',
            question: many
                ? `Supprimer définitivement ces ${pending?.items.length} dossiers de votre espace ? Cette action est irréversible : ils ne pourront plus être restaurés. Les destinataires les conservent.`
                : `Supprimer définitivement le dossier ${pending?.items[0].reference} de votre espace ? Cette action est irréversible : il ne pourra plus être restauré. Les destinataires le conservent.`,
            label: 'Supprimer définitivement',
        },
    }[pending?.kind ?? 'restore'];

    return (
        <>
            <Head title={title} />

            <div className="list-page">
            <PageHeader
                title="Corbeille"
                kicker="Demande"
                subtitle="Les dossiers que vous avez supprimés. Ils sont retirés de votre espace uniquement : les destinataires les conservent, avec leur historique."
                actions={<a href={requestsUrl} className="btn-secondary">Retour aux demandes</a>}
            />

            {selected.length > 0 && (
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-800" role="status">
                    <span>{selected.length} dossier{selected.length > 1 ? 's' : ''} sélectionné{selected.length > 1 ? 's' : ''}</span>
                    <div className="flex flex-wrap gap-2">
                        <button type="button" className="btn-secondary px-3 py-1.5" disabled={busy !== null} onClick={() => setPending({ kind: 'restore', items: rows.filter((row) => selected.includes(row.id)) })}>
                            <RotateCcw className="size-4" aria-hidden="true" /> Restaurer la sélection
                        </button>
                        <button type="button" className="btn-danger px-3 py-1.5" disabled={busy !== null} onClick={() => setPending({ kind: 'destroy', items: rows.filter((row) => selected.includes(row.id)) })}>
                            <Trash2 className="size-4" aria-hidden="true" /> Supprimer définitivement la sélection
                        </button>
                    </div>
                </div>
            )}

            <div className="card list-scroll p-0">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th className="w-10">
                                <input type="checkbox" className="size-4 rounded border-slate-300" aria-label="Tout sélectionner" checked={allChecked} disabled={rows.length === 0} onChange={(event) => setSelected(event.target.checked ? rows.map((row) => row.id) : [])} />
                            </th>
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
                                <td colSpan="6" className="py-12 text-center text-slate-500">
                                    <span className="mx-auto mb-2 block w-fit text-slate-300 dark:text-slate-600"><Icon name="trash" className="size-8" /></span>
                                    La corbeille est vide.
                                </td>
                            </tr>
                        )}

                        {dossiers.data.map((dossier) => (
                            <tr key={dossier.id} className={busy ? 'opacity-70' : ''}>
                                <td className="w-10"><input type="checkbox" className="size-4 rounded border-slate-300" aria-label={`Sélectionner ${dossier.reference}`} checked={selected.includes(dossier.id)} onChange={(event) => toggle(dossier.id, event.target.checked)} /></td>
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
                                            onClick={() => setPending({ kind: 'restore', items: [dossier] })}
                                        >
                                            <Icon name="restore" className="size-4" /> Restaurer
                                        </button>
                                        <button
                                            type="button"
                                            className="btn-danger px-3 py-1.5"
                                            disabled={busy !== null}
                                            onClick={() => setPending({ kind: 'destroy', items: [dossier] })}
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
            </div>

            <ConfirmDialog
                open={pending !== null}
                onOpenChange={(open) => !open && setPending(null)}
                danger={pending?.kind === 'destroy'}
                Icon={pending?.kind === 'destroy' ? Trash2 : RotateCcw}
                title={wording.title}
                question={wording.question}
                confirmLabel={wording.label}
                onConfirm={confirm}
                processing={busy !== null}
            />
        </>
    );
}
