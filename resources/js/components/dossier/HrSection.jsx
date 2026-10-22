import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Archive, UserRound } from 'lucide-react';
import ConfirmDialog from '@/components/app/ConfirmDialog';
import SectionTitle from '@/components/app/SectionTitle';
import StatusBadge from '@/components/app/StatusBadge';
import { ATTACHMENT_TYPES } from '@/components/form/AttachmentsField';
import Field from '@/components/form/Field';
import TextareaField from '@/components/form/TextareaField';

const SELECT = 'block w-full rounded-lg border bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-brand-500/30 dark:bg-slate-900';

/** Suivi RH d'une demande externe : décisions reçues, réponse officielle, archivage. */
export default function HrSection({ hr }) {
    const form = useForm({ content: '', channel: '', sent_at: hr.today, document: null });
    const [confirmArchive, setConfirmArchive] = useState(false);
    const [archiving, setArchiving] = useState(false);

    const submit = (event) => {
        event.preventDefault();
        form.post(hr.respondUrl, { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
    };

    const archive = () => {
        setArchiving(true);
        router.post(hr.archiveUrl, {}, { preserveScroll: true, onFinish: () => {
            setArchiving(false);
            setConfirmArchive(false);
        } });
    };

    return (
        <section className="card border-t-4 border-t-violet-500 dark:border-t-violet-400" aria-labelledby="hr-title">
            <SectionTitle id="hr-title" Icon={UserRound}>
                Suivi RH
            </SectionTitle>

            <div className="mt-3 flex flex-wrap gap-2 text-sm">
                <StatusBadge color="green">{hr.positive} décision(s) positive(s)</StatusBadge>
                <StatusBadge color="red">{hr.negative} décision(s) négative(s)</StatusBadge>
                <StatusBadge>{hr.pending} traitement(s) en attente de réponse</StatusBadge>
            </div>
            <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">Toutes les décisions sont conservées, même divergentes : aucune majorité n’est calculée.</p>

            {hr.canRecordOfficialResponse ? (
                <form onSubmit={submit} className="mt-5 space-y-4">
                    <h3 className="font-semibold">Enregistrer la réponse officielle</h3>
                    <TextareaField id="official-content" name="content" label="Réponse envoyée au demandeur" rows={5} required value={form.data.content} onChange={(value) => form.setData('content', value)} error={form.errors.content} />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="channel" className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                Canal de réponse <span className="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                            </label>
                            <select
                                id="channel"
                                name="channel"
                                required
                                value={form.data.channel}
                                onChange={(event) => form.setData('channel', event.target.value)}
                                className={`${SELECT} ${form.errors.channel ? 'border-red-400' : 'border-slate-300 focus:border-brand-500 dark:border-slate-700'}`}
                            >
                                <option value="">Sélectionner</option>
                                {hr.channels.map((channel) => (
                                    <option key={channel.value} value={channel.value}>
                                        {channel.label}
                                    </option>
                                ))}
                            </select>
                            {form.errors.channel && <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{form.errors.channel}</p>}
                        </div>
                        <Field name="sent_at" label="Date d’envoi" type="date" required max={hr.today} value={form.data.sent_at} onChange={(value) => form.setData('sent_at', value)} error={form.errors.sent_at} />
                    </div>
                    <Field name="document" label="Document (facultatif)" type="file" accept={ATTACHMENT_TYPES} onChange={(file) => form.setData('document', file)} error={form.errors.document} />
                    <button type="submit" className="btn-primary" disabled={form.processing}>
                        Enregistrer la réponse officielle
                    </button>
                </form>
            ) : (
                hr.waitingForDecision && <p className="mt-4 text-sm text-slate-500 dark:text-slate-400">La réponse officielle pourra être enregistrée dès qu’un responsable aura rendu sa décision.</p>
            )}

            {hr.canArchive && (
                <div className="mt-5">
                    <button type="button" className="btn-secondary" onClick={() => setConfirmArchive(true)}>
                        Archiver le dossier
                    </button>
                    <ConfirmDialog
                        open={confirmArchive}
                        onOpenChange={setConfirmArchive}
                        Icon={Archive}
                        title="Archiver le dossier"
                        question="Archiver définitivement ce dossier ?"
                        confirmLabel="Archiver"
                        onConfirm={archive}
                        processing={archiving}
                    />
                </div>
            )}
        </section>
    );
}
