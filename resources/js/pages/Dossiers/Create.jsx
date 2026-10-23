import { useMemo, useRef, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Bell, Clock, Info, Paperclip, Send } from 'lucide-react';
import ConfirmDialog from '@/components/app/ConfirmDialog';
import AttachmentsField, { attachmentsError } from '@/components/form/AttachmentsField';
import Field from '@/components/form/Field';
import FormColumns, { FormPanel } from '@/components/form/FormColumns';
import FormSection from '@/components/form/FormSection';
import RecipientPicker, { flattenDirectory, recipientLabel } from '@/components/form/RecipientPicker';
import TextareaField from '@/components/form/TextareaField';

const SELECT = 'block w-full rounded-lg border bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-brand-500/30 dark:bg-slate-900';
const localDate = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

/** Premier message d'erreur d'un champ ou de l'un de ses éléments (« recipients.0 »…). */
const firstError = (errors, name) => errors[name] ?? Object.entries(errors).find(([key]) => key.startsWith(`${name}.`))?.[1];

/**
 * Nouvelle demande interne (React) : deux colonnes numérotées, avancement,
 * échéance proposée selon l'urgence, type « Autre » à préciser, formulaire
 * complet pour certains types, confirmation avant l'envoi et jeton à usage
 * unique contre le double envoi. Les règles restent côté Laravel
 * (StoreInternalRequest, SubmissionGuard).
 */
export default function Create({ requestTypes, urgencyLevels, directory, completeTypes, otherTypeId, today, submission, urls }) {
    const formRef = useRef(null);
    const files = useRef(null);
    const [dueTouched, setDueTouched] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [recipientError, setRecipientError] = useState(false);
    const people = useMemo(() => flattenDirectory(directory), [directory]);

    const form = useForm({
        subject: '',
        request_type_id: '',
        request_type_other: '',
        urgency_level_id: '',
        due_at: '',
        description: '',
        recipients: [],
        attachments: [],
    });
    const { data, errors } = form;

    const isOther = otherTypeId !== null && String(data.request_type_id) === String(otherTypeId);
    const complete = completeTypes.map(String).includes(String(data.request_type_id));
    const done1 = Boolean(data.subject.trim() && data.request_type_id && data.due_at && data.due_at >= today && (!isOther || data.request_type_other.trim()) && (!complete || data.urgency_level_id));
    const done2 = data.recipients.length > 0 && (!complete || Boolean(data.description.trim()));
    const filled = Number(done1) + Number(done2);
    const blocked = complete && filled < 2;
    const names = data.recipients.map((id) => people.find((user) => user.id === id)).filter(Boolean).map(recipientLabel);

    // Échéance proposée d'après l'urgence, tant que l'utilisateur ne l'a pas modifiée.
    const chooseUrgency = (value) => {
        form.setData((current) => {
            const level = urgencyLevels.find((item) => String(item.id) === String(value));
            if (!level || dueTouched) {
                return { ...current, urgency_level_id: value };
            }
            const due = new Date();
            due.setHours(due.getHours() + level.response_delay_hours);

            return { ...current, urgency_level_id: value, due_at: localDate(due) };
        });
    };

    /** Amène l'utilisateur au premier champ manquant (colonne de gauche d'abord). */
    const focusFirstInvalid = () => {
        const fieldsOf = (step) => [...(formRef.current?.querySelectorAll(`[data-step="${step}"] input, [data-step="${step}"] select, [data-step="${step}"] textarea`) ?? [])].filter((field) => !field.disabled);
        const invalid = fieldsOf(1).find((field) => !field.checkValidity());
        if (invalid) {
            invalid.closest('[data-step]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(() => invalid.reportValidity(), 0);
            return true;
        }
        if (data.recipients.length === 0) {
            setRecipientError(true);
            formRef.current?.querySelector('[data-step="2"]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return true;
        }
        const invalid2 = fieldsOf(2).find((field) => !field.checkValidity());
        if (invalid2) {
            invalid2.closest('[data-step]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(() => invalid2.reportValidity(), 0);
            return true;
        }
        return false;
    };

    const submit = (event) => {
        event.preventDefault();
        if (form.processing) {
            return;
        }
        if (focusFirstInvalid() || blocked) {
            return;
        }
        setRecipientError(false);
        setConfirming(true);
    };

    const send = () => {
        form.transform((values) => ({ ...values, _submission: submission }));
        form.post(urls.store, {
            forceFormData: true,
            onError: () => setConfirming(false),
            onSuccess: () => setConfirming(false),
        });
    };

    const hasError = (...fields) => fields.some((name) => firstError(errors, name));

    return (
        <>
            <Head title="Nouvelle demande" />

            <FormColumns
                title="Nouvelle demande interne"
                kicker="Demande"
                subtitle="Décrivez votre demande, fixez son échéance, puis choisissez les personnes qui doivent la traiter."
                backUrl={urls.back}
                backLabel="Mes demandes"
                cancelUrl={urls.back}
                panels={2}
                filled={filled}
                blocked={blocked}
                recipientError={recipientError}
                hasErrors={form.hasErrors}
                processing={form.processing}
                submitLabel="Envoyer la demande"
                SubmitIcon={Send}
                onSubmit={submit}
                formRef={formRef}
            >
                {/* Étape 1 : objet, type, urgence et échéance */}
                <FormPanel number={1} title="Demande et échéance" description="Ce qui est demandé, son type, son urgence et la date de réponse attendue." done={done1} hasError={hasError('subject', 'request_type_id', 'request_type_other', 'urgency_level_id', 'due_at')}>
                    <Field name="subject" label="Objet" required maxLength={255} placeholder="Ex. : Demande de maintenance de la chaîne n°2" value={data.subject} onChange={(value) => form.setData('subject', value)} error={errors.subject} />

                    <div className="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label htmlFor="request_type_id" className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                Type de demande <span className="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                            </label>
                            <select id="request_type_id" name="request_type_id" required value={data.request_type_id} onChange={(event) => form.setData('request_type_id', event.target.value)} className={`${SELECT} ${errors.request_type_id ? 'border-red-400' : 'border-slate-300 focus:border-brand-500 dark:border-slate-700'}`}>
                                <option value="">Sélectionner</option>
                                {requestTypes.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </select>
                            {errors.request_type_id && <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.request_type_id}</p>}
                        </div>
                        <div>
                            <label htmlFor="urgency_level_id" className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                Urgence
                                {complete && (
                                    <span className="text-red-600 dark:text-red-400" aria-hidden="true">
                                        {' '}*
                                    </span>
                                )}
                            </label>
                            <select id="urgency_level_id" name="urgency_level_id" required={complete} value={data.urgency_level_id} onChange={(event) => chooseUrgency(event.target.value)} className={`${SELECT} ${errors.urgency_level_id ? 'border-red-400' : 'border-slate-300 focus:border-brand-500 dark:border-slate-700'}`}>
                                <option value="">Non précisée</option>
                                {urgencyLevels.map((level) => (
                                    <option key={level.id} value={level.id}>
                                        {level.name}
                                    </option>
                                ))}
                            </select>
                            {errors.urgency_level_id && <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.urgency_level_id}</p>}
                        </div>
                    </div>

                    {isOther && (
                        <div data-testid="other-request-type">
                            <Field
                                name="request_type_other"
                                label="Précisez le type de demande"
                                required
                                maxLength={150}
                                placeholder="Ex. : Location de salle, Autorisation d’absence…"
                                hint="Ce texte remplace « Autre » : c’est le type que verront les destinataires."
                                value={data.request_type_other}
                                onChange={(value) => form.setData('request_type_other', value)}
                                error={errors.request_type_other}
                            />
                        </div>
                    )}

                    {complete && (
                        <p role="status" className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/60 dark:bg-amber-500/10 dark:text-amber-200">
                            <Info className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            <span>
                                Pour ce type de demande, <strong>tous les champs</strong> du formulaire sont obligatoires (marqués <span className="text-red-600 dark:text-red-400">*</span>).
                            </span>
                        </p>
                    )}

                    <div className="rounded-xl border border-univ-line bg-univ-surface/60 p-4 dark:border-slate-800 dark:bg-slate-950/30">
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="w-full sm:w-56">
                                <label htmlFor="due_at" className="mb-1.5 flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                                    <Clock className="size-4 text-brand-500 dark:text-brand-300" aria-hidden="true" />
                                    Date d’échéance <span className="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                                </label>
                                <input
                                    id="due_at"
                                    type="date"
                                    name="due_at"
                                    required
                                    min={today}
                                    value={data.due_at}
                                    onChange={(event) => {
                                        setDueTouched(true);
                                        form.setData('due_at', event.target.value);
                                    }}
                                    className="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
                                />
                            </div>
                            <p className="min-w-0 flex-1 text-xs text-univ-muted dark:text-slate-400">Proposée automatiquement d’après l’urgence tant que vous ne la modifiez pas. Les destinataires sont alertés si elle est dépassée.</p>
                        </div>
                        {errors.due_at && <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.due_at}</p>}
                    </div>
                </FormPanel>

                {/* Étape 2 : destinataires, description, pièces jointes */}
                <FormPanel number={2} title="Destinataires et description" description="Les personnes qui traiteront la demande, le détail et les pièces utiles." done={done2} hasError={hasError('recipients', 'description', 'attachments')}>
                    <div className="space-y-2">
                        <RecipientPicker
                            directory={directory}
                            selected={data.recipients}
                            onChange={(list) => {
                                form.setData('recipients', list);
                                if (list.length) setRecipientError(false);
                            }}
                            error={firstError(errors, 'recipients')}
                        />
                        <p className="flex items-center gap-1.5 text-xs text-univ-muted dark:text-slate-400">
                            <Bell className="size-3.5" aria-hidden="true" /> Chaque destinataire est prévenu par notification dès l’envoi de la demande.
                        </p>
                    </div>

                    <TextareaField name="description" label="Détail de la demande" rows={6} required={complete} placeholder="Contexte, éléments attendus, contraintes éventuelles…" value={data.description} onChange={(value) => form.setData('description', value)} error={errors.description} />

                    <FormSection title="Pièces jointes (facultatif)" Icon={Paperclip} defaultOpen={false} hasErrors={Boolean(attachmentsError(errors))}>
                        <AttachmentsField ref={files} onChange={(list) => form.setData('attachments', list)} error={attachmentsError(errors)} />
                    </FormSection>
                </FormPanel>
            </FormColumns>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Confirmer l’envoi"
                question="Êtes-vous sûr de vouloir envoyer cette demande à"
                names={names}
                subject={data.subject.trim()}
                confirmLabel="Confirmer l’envoi"
                onConfirm={send}
                processing={form.processing}
            />
        </>
    );
}
