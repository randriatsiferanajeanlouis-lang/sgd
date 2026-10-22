import { useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Download, Paperclip } from 'lucide-react';
import SectionTitle from '@/components/app/SectionTitle';
import StatusBadge from '@/components/app/StatusBadge';
import AttachmentsField, { ATTACHMENT_TYPES, attachmentsError } from '@/components/form/AttachmentsField';
import DocumentLink from '@/components/dossier/DocumentLink';
import { cn } from '@/lib/utils';

const TONES = {
    pdf: 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
    word: 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    excel: 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300',
    other: 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
};

/** Une pièce et ses versions : Remplacer, Annuler, Versions selon les droits. */
function DocumentItem({ document }) {
    const [panel, setPanel] = useState(null);
    const replace = useForm({ file: null });
    const cancel = useForm({ reason: '' });
    const toggle = (name) => setPanel((current) => (current === name ? null : name));

    return (
        <li className="rounded-lg border border-univ-line p-3 transition hover:border-brand-300 dark:border-slate-800 dark:hover:border-slate-600">
            <div className="flex items-start gap-3">
                <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-md text-[10px] font-extrabold', TONES[document.kind])} aria-hidden="true">
                    {document.extension}
                </span>
                <div className="min-w-0 flex-1 break-words">
                    <DocumentLink document={document} withIcon={false} />
                    <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {document.size && `${document.size} · `}
                        {document.uploader} · {document.date}
                        {document.responseAuthor && ` · réponse de ${document.responseAuthor}`}
                    </div>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1">
                    <StatusBadge>V{document.version}</StatusBadge>
                    <StatusBadge color={document.status.color}>{document.status.label}</StatusBadge>
                </div>
            </div>

            {document.cancellation && (
                <p className="mt-2 text-xs text-red-700 dark:text-red-300">
                    Annulé par {document.cancellation.by} le {document.cancellation.date} : {document.cancellation.reason}
                </p>
            )}

            <div className="mt-2 flex flex-wrap gap-3 text-xs">
                {document.canReplace && (
                    <button type="button" className="text-brand-600 hover:underline dark:text-brand-300" onClick={() => toggle('replace')}>
                        Remplacer
                    </button>
                )}
                {document.canCancel && (
                    <button type="button" className="text-red-600 hover:underline dark:text-red-400" onClick={() => toggle('cancel')}>
                        Annuler
                    </button>
                )}
                {document.versions.length > 1 && (
                    <button type="button" className="text-slate-600 hover:underline dark:text-slate-300" onClick={() => toggle('versions')}>
                        Versions ({document.versions.length})
                    </button>
                )}
            </div>

            {document.canReplace && panel === 'replace' && (
                <form
                    className="mt-3 flex flex-wrap items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        replace.post(document.replaceUrl, { forceFormData: true, preserveScroll: true, onSuccess: () => setPanel(null) });
                    }}
                >
                    <input type="file" name="file" required className="text-xs" aria-label="Nouvelle version" accept={ATTACHMENT_TYPES} onChange={(event) => replace.setData('file', event.target.files[0] ?? null)} />
                    <button type="submit" className="btn-primary px-3 py-1" disabled={replace.processing}>
                        Envoyer V{document.version + 1}
                    </button>
                    {replace.errors.file && <p className="w-full text-xs text-red-600 dark:text-red-400">{replace.errors.file}</p>}
                </form>
            )}

            {document.canCancel && panel === 'cancel' && (
                <form
                    className="mt-3 flex flex-wrap items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        cancel.post(document.cancelUrl, { preserveScroll: true, onSuccess: () => setPanel(null) });
                    }}
                >
                    <input
                        type="text"
                        name="reason"
                        required
                        placeholder="Motif de l’annulation"
                        aria-label="Motif de l’annulation"
                        value={cancel.data.reason}
                        onChange={(event) => cancel.setData('reason', event.target.value)}
                        className="min-w-48 flex-1 rounded-lg border border-slate-300 bg-white px-2 py-1 dark:border-slate-700 dark:bg-slate-900"
                    />
                    <button type="submit" className="btn-danger px-3 py-1" disabled={cancel.processing}>
                        Confirmer l’annulation
                    </button>
                    {cancel.errors.reason && <p className="w-full text-xs text-red-600 dark:text-red-400">{cancel.errors.reason}</p>}
                </form>
            )}

            {document.versions.length > 1 && panel === 'versions' && (
                <div className="mt-3 overflow-x-auto">
                    <table className="data-table text-xs">
                        <thead>
                            <tr>
                                <th>Version</th>
                                <th>Nom</th>
                                <th>Ajouté par</th>
                                <th>Date</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            {document.versions.map((version) => (
                                <tr key={version.id}>
                                    <td data-label="Version">V{version.version}</td>
                                    <td data-label="Nom">
                                        {version.url ? (
                                            <a href={version.url} className="text-brand-600 hover:underline dark:text-brand-300">
                                                {version.name}
                                            </a>
                                        ) : (
                                            version.name
                                        )}
                                    </td>
                                    <td data-label="Ajouté par">{version.uploader}</td>
                                    <td data-label="Date">{version.date}</td>
                                    <td data-label="Statut">
                                        <StatusBadge color={version.status.color}>{version.status.label}</StatusBadge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </li>
    );
}

/** Pièces du dossier : liste, téléchargement groupé, ajout selon les droits. */
export default function Documents({ documents, can, urls }) {
    const files = useRef(null);
    const form = useForm({ attachments: [] });

    return (
        <section className="card" aria-labelledby="documents-title">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <SectionTitle id="documents-title" Icon={Paperclip} count={documents.length}>
                    Pièces du dossier
                </SectionTitle>
                {can.downloadAll && (
                    <a href={urls.zip} className="btn-secondary px-3 py-1.5">
                        <Download className="size-4" aria-hidden="true" /> Télécharger toutes les pièces
                    </a>
                )}
            </div>

            <ul className="mt-5 space-y-3 text-sm">
                {documents.map((document) => (
                    <DocumentItem key={document.key} document={document} />
                ))}
                {documents.length === 0 && <li className="text-slate-500">Aucune pièce jointe.</li>}
            </ul>

            {can.addDocument && (
                <form
                    className="mt-5 space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(urls.addDocument, {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset();
                                if (files.current) files.current.value = '';
                            },
                        });
                    }}
                >
                    <AttachmentsField ref={files} id="new-attachments" label="Ajouter des pièces" onChange={(list) => form.setData('attachments', list)} error={attachmentsError(form.errors)} />
                    <button type="submit" className="btn-secondary" disabled={form.processing}>
                        Ajouter
                    </button>
                </form>
            )}
        </section>
    );
}
