import { useRef, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Inbox, Info } from 'lucide-react';
import ConfirmDialog from '@/components/app/ConfirmDialog';
import DeadlineBadge from '@/components/app/DeadlineBadge';
import StatusBadge from '@/components/app/StatusBadge';
import SectionTitle from '@/components/app/SectionTitle';
import AttachmentsField, { attachmentsError } from '@/components/form/AttachmentsField';
import Field from '@/components/form/Field';
import TextareaField from '@/components/form/TextareaField';

const DETAILS = 'mt-4 rounded-lg border border-slate-200 p-4 dark:border-slate-700';

/** Transmettre au responsable (avec confirmation). */
function RequestValidation({ treatment }) {
    const form = useForm({ comment: '' });
    const [confirming, setConfirming] = useState(false);
    const name = treatment.validatorName ? `${treatment.validatorName} (votre responsable)` : null;

    const send = () =>
        form.post(treatment.urls.validation, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onFinish: () => setConfirming(false),
        });

    return (
        <details className={DETAILS}>
            <summary className="cursor-pointer font-medium">Transmettre au responsable</summary>
            <form
                className="mt-3 space-y-3"
                noValidate
                onSubmit={(event) => {
                    event.preventDefault();
                    if (!form.processing) setConfirming(true);
                }}
            >
                <p className="text-sm text-slate-500 dark:text-slate-400">Votre responsable ({treatment.validatorName}) validera ou refusera avant votre réponse.</p>
                <TextareaField id="validation-comment" name="comment" label="Commentaire pour le responsable (facultatif)" rows={3} value={form.data.comment} onChange={(value) => form.setData('comment', value)} error={form.errors.comment} />
                <button type="submit" className="btn-secondary" disabled={form.processing}>
                    Transmettre au responsable
                </button>
            </form>
            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Confirmer la transmission"
                question="Êtes-vous sûr de vouloir transmettre ce dossier pour validation à"
                names={name ? [name] : []}
                confirmLabel="Confirmer la transmission"
                onConfirm={send}
                processing={form.processing}
            />
        </details>
    );
}

/** Transmettre à un autre utilisateur (destinataire obligatoire, confirmation). */
function Forward({ treatment }) {
    const form = useForm({ user_id: '', comment: '' });
    const [confirming, setConfirming] = useState(false);
    const [missing, setMissing] = useState(false);
    const target = treatment.forwardTargets.find((option) => option.value === form.data.user_id);

    const send = () =>
        form.post(treatment.urls.forward, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onFinish: () => setConfirming(false),
        });

    return (
        <details className={DETAILS}>
            <summary className="cursor-pointer font-medium">Transmettre à un autre utilisateur</summary>
            <form
                className="mt-3 space-y-3"
                noValidate
                onSubmit={(event) => {
                    event.preventDefault();
                    if (form.processing) return;
                    if (!target) {
                        setMissing(true);
                        return;
                    }
                    setConfirming(true);
                }}
            >
                <div>
                    <label htmlFor="user_id" className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Destinataire <span className="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                    </label>
                    <select
                        id="user_id"
                        name="user_id"
                        required
                        value={form.data.user_id}
                        onChange={(event) => {
                            form.setData('user_id', event.target.value);
                            setMissing(false);
                        }}
                        className={`block w-full rounded-lg border bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-brand-500/30 dark:bg-slate-900 ${form.errors.user_id ? 'border-red-400' : 'border-slate-300 focus:border-brand-500 dark:border-slate-700'}`}
                    >
                        <option value="">Sélectionner</option>
                        {treatment.forwardTargets.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    {form.errors.user_id && <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{form.errors.user_id}</p>}
                </div>
                <Field name="forward-comment" label="Commentaire (facultatif)" value={form.data.comment} onChange={(value) => form.setData('comment', value)} error={form.errors.comment} />
                {missing && (
                    <p className="flex items-center gap-1.5 text-sm font-semibold text-red-600 dark:text-red-400" role="alert">
                        <Info className="size-4" aria-hidden="true" /> Aucun destinataire sélectionné : choisissez la personne à qui transmettre le dossier.
                    </p>
                )}
                <button type="submit" className="btn-secondary" disabled={form.processing}>
                    Transmettre
                </button>
            </form>
            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Confirmer la transmission"
                question="Êtes-vous sûr de vouloir transmettre ce dossier à"
                names={target ? [target.label] : []}
                confirmLabel="Confirmer la transmission"
                onConfirm={send}
                processing={form.processing}
            />
        </details>
    );
}

/** Réponse (interne) ou décision (demande externe) avec pièces jointes. */
function Respond({ treatment, external }) {
    const files = useRef(null);
    const form = useForm({ decision: '', content: '', justification: '', recommendation: '', attachments: [] });

    const submit = (event) => {
        event.preventDefault();
        form.transform((data) => {
            const payload = { content: data.content, attachments: data.attachments };
            return external ? { ...payload, decision: data.decision, justification: data.justification, recommendation: data.recommendation } : payload;
        });
        form.post(treatment.urls.respond, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                if (files.current) files.current.value = '';
            },
        });
    };

    return (
        <form onSubmit={submit} className="mt-4 space-y-4">
            {external && (
                <fieldset>
                    <legend className="mb-2 text-sm font-medium">Décision</legend>
                    <div className="flex gap-4">
                        {treatment.decisions.map((decision) => (
                            <label key={decision.value} className="flex items-center gap-2">
                                <input type="radio" name="decision" value={decision.value} checked={form.data.decision === decision.value} onChange={() => form.setData('decision', decision.value)} required />
                                {decision.label}
                            </label>
                        ))}
                    </div>
                    {form.errors.decision && <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{form.errors.decision}</p>}
                </fieldset>
            )}

            <TextareaField id="content" name="content" label={external ? 'Commentaire' : 'Réponse'} rows={5} required value={form.data.content} onChange={(value) => form.setData('content', value)} error={form.errors.content} />

            {external && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <TextareaField name="justification" label="Justification (facultatif)" rows={3} value={form.data.justification} onChange={(value) => form.setData('justification', value)} error={form.errors.justification} />
                    <TextareaField name="recommendation" label="Recommandation (facultatif)" rows={3} value={form.data.recommendation} onChange={(value) => form.setData('recommendation', value)} error={form.errors.recommendation} />
                </div>
            )}

            <AttachmentsField ref={files} label="Pièce(s) jointe(s) (facultatif)" onChange={(list) => form.setData('attachments', list)} error={attachmentsError(form.errors)} />

            <button type="submit" className="btn-primary" disabled={form.processing}>
                {external ? 'Envoyer la décision au RH' : 'Envoyer la réponse'}
            </button>
        </form>
    );
}

/** « Mon traitement » : section du destinataire connecté. */
export default function MyTreatment({ treatment, external }) {
    const [archiving, setArchiving] = useState(false);

    return (
        <section className="card border-t-4 border-t-brand-500 dark:border-t-brand-400" aria-labelledby="my-treatment-title">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <SectionTitle id="my-treatment-title" Icon={Inbox}>
                    Mon traitement
                </SectionTitle>
                <div className="flex items-center gap-2">
                    <StatusBadge color={treatment.status.color}>{treatment.status.label}</StatusBadge>
                    <DeadlineBadge deadline={treatment.deadline} />
                </div>
            </div>

            {treatment.pendingValidation && (
                <p className="mt-4 rounded-lg bg-amber-50 px-4 py-2 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">Dossier transmis à votre responsable : vous pourrez répondre après sa décision.</p>
            )}

            {treatment.canRequestValidation && <RequestValidation treatment={treatment} />}
            {treatment.canForward && <Forward treatment={treatment} />}
            {treatment.canRespond && <Respond treatment={treatment} external={external} />}

            {treatment.canArchive && (
                <div className="mt-4">
                    <button
                        type="button"
                        className="btn-secondary"
                        disabled={archiving}
                        onClick={() => {
                            setArchiving(true);
                            router.post(treatment.urls.archive, {}, { preserveScroll: true, onFinish: () => setArchiving(false) });
                        }}
                    >
                        Archiver mon traitement
                    </button>
                </div>
            )}
        </section>
    );
}
