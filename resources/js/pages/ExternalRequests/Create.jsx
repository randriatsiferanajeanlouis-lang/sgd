import { useMemo, useRef, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Bell, Calendar, Clock, Info, Paperclip, Send, UserRound } from 'lucide-react';
import ConfirmDialog from '@/components/app/ConfirmDialog';
import AttachmentsField, { attachmentsError } from '@/components/form/AttachmentsField';
import Field from '@/components/form/Field';
import FormColumns, { FormPanel } from '@/components/form/FormColumns';
import FormSection from '@/components/form/FormSection';
import RecipientPicker, { flattenDirectory, recipientLabel } from '@/components/form/RecipientPicker';
import TextareaField from '@/components/form/TextareaField';

const SELECT = 'block w-full rounded-lg border bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-brand-500/30 dark:bg-slate-900';
const FIELDSET = 'rounded-xl border border-univ-line bg-univ-surface/60 p-4 dark:border-slate-800 dark:bg-slate-950/30';
const LEGEND = 'flex items-center gap-2 px-1 text-sm font-semibold text-univ-ink dark:text-white';
const EMAIL = /^[^\s@]+@[^\s@]+$/;

/** Premier message d'erreur d'un champ ou de l'un de ses éléments (« recipients.0 »…). */
const firstError = (errors, name) => errors[name] ?? Object.entries(errors).find(([key]) => key.startsWith(`${name}.`))?.[1];

/** Liste déroulante (même rendu que <x-form.select>). */
function SelectField({ name, label, value, onChange, options, placeholder = 'Sélectionner', required = false, error }) {
    return (
        <div>
            <label htmlFor={name} className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                {label}
                {required && (
                    <span className="text-red-600 dark:text-red-400" aria-hidden="true">
                        {' '}*
                    </span>
                )}
            </label>
            <select id={name} name={name} required={required} value={value} onChange={(event) => onChange(event.target.value)} className={`${SELECT} ${error ? 'border-red-400' : 'border-slate-300 focus:border-brand-500 dark:border-slate-700'}`}>
                <option value="">{placeholder}</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {error && <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{error}</p>}
        </div>
    );
}

/**
 * Nouvelle demande externe (React), réservée au RH : demande et demandeur,
 * réception (date, canal, urgence → échéance calculée), responsables
 * destinataires, description et pièces jointes. Confirmation avant l'envoi et
 * jeton à usage unique ; règles et calcul de l'échéance qui font foi côté
 * Laravel (StoreExternalRequest, ExternalRequestService, SubmissionGuard).
 */
export default function Create({ requestTypes, channels, urgencyLevels, directory, completeTypes, otherTypeId, today, submission, urls }) {
    const formRef = useRef(null);
    const files = useRef(null);
    const [confirming, setConfirming] = useState(false);
    const [recipientError, setRecipientError] = useState(false);
    const people = useMemo(() => flattenDirectory(directory), [directory]);

    const form = useForm({
        subject: '',
        request_type_id: '',
        request_type_other: '',
        reception_channel: '',
        requester_name: '',
        requester_organization: '',
        requester_phone: '',
        requester_email: '',
        received_at: today,
        urgency_level_id: '',
        recipients: [],
        description: '',
        attachments: [],
    });
    const { data, errors } = form;
    const set = (name) => (value) => form.setData(name, value);

    const isOther = otherTypeId !== null && String(data.request_type_id) === String(otherTypeId);
    const complete = completeTypes.map(String).includes(String(data.request_type_id));
    const emailOk = !data.requester_email || EMAIL.test(data.requester_email);
    const done1 = Boolean(
        data.subject.trim() &&
            data.request_type_id &&
            data.reception_channel &&
            data.requester_name.trim() &&
            emailOk &&
            (!isOther || data.request_type_other.trim()) &&
            (!complete || (data.requester_organization.trim() && data.requester_phone.trim() && data.requester_email.trim())),
    );
    const done2 = Boolean(data.received_at && data.received_at <= today && data.urgency_level_id && data.recipients.length > 0 && (!complete || data.description.trim()));
    const filled = Number(done1) + Number(done2);
    const blocked = complete && filled < 2;
    const names = data.recipients.map((id) => people.find((user) => user.id === id)).filter(Boolean).map(recipientLabel);

    // Aperçu de l'échéance : date de réception + délai de l'urgence (le calcul qui fait foi est refait par Laravel).
    const preview = (() => {
        const level = urgencyLevels.find((item) => String(item.id) === String(data.urgency_level_id));
        if (!level || !data.received_at) {
            return 'Choisissez la date de réception et l’urgence.';
        }
        const now = new Date();
        const due = new Date(`${data.received_at}T${now.toTimeString().slice(0, 8)}`);
        due.setHours(due.getHours() + level.response_delay_hours);

        return due.toLocaleString('fr-FR', { dateStyle: 'full', timeStyle: 'short' });
    })();

    /** Amène l'utilisateur au premier champ manquant (colonne de gauche d'abord). */
    const focusFirstInvalid = () => {
        const fieldsOf = (step) => [...(formRef.current?.querySelectorAll(`[data-step="${step}"] input, [data-step="${step}"] select, [data-step="${step}"] textarea`) ?? [])].filter((field) => !field.disabled);
        const report = (field) => {
            field.closest('[data-step]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(() => field.reportValidity(), 0);
            return true;
        };
        const invalid1 = fieldsOf(1).find((field) => !field.checkValidity());
        if (invalid1) return report(invalid1);
        const invalid2 = fieldsOf(2).find((field) => !field.checkValidity() && !field.closest('[data-recipient-picker]'));
        if (invalid2) return report(invalid2);
        if (data.recipients.length === 0) {
            setRecipientError(true);
            formRef.current?.querySelector('[data-recipient-picker]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return true;
        }
        return false;
    };

    const submit = (event) => {
        event.preventDefault();
        if (form.processing || focusFirstInvalid() || blocked) {
            return;
        }
        setRecipientError(false);
        setConfirming(true);
    };

    const send = () => {
        form.transform((values) => ({ ...values, _submission: submission }));
        form.post(urls.store, { forceFormData: true, onError: () => setConfirming(false), onSuccess: () => setConfirming(false) });
    };

    const hasError = (...fields) => fields.some((name) => firstError(errors, name));

    return (
        <>
            <Head title="Nouvelle demande externe" />

            <FormColumns
                title="Nouvelle demande externe"
                kicker="Demandes externes"
                subtitle="Enregistrez la demande reçue d’une personne ou d’un organisme extérieur, puis transmettez-la au(x) responsable(s) concerné(s)."
                backUrl={urls.back}
                backLabel="Demandes externes"
                cancelUrl={urls.back}
                panels={2}
                filled={filled}
                blocked={blocked}
                recipientError={recipientError}
                hasErrors={form.hasErrors}
                processing={form.processing}
                submitLabel="Enregistrer et transmettre"
                SubmitIcon={Send}
                onSubmit={submit}
                formRef={formRef}
            >
                {/* Étape 1 : objet, type, canal et demandeur */}
                <FormPanel number={1} title="Demande et demandeur" description="Ce qui est demandé, par quel canal et par qui." done={done1} hasError={hasError('subject', 'request_type_id', 'request_type_other', 'reception_channel', 'requester_name', 'requester_organization', 'requester_phone', 'requester_email')}>
                    <Field name="subject" label="Objet" required maxLength={255} placeholder="Ex. : Demande de stage, réclamation, partenariat…" value={data.subject} onChange={set('subject')} error={errors.subject} />

                    <div className="grid gap-5 sm:grid-cols-2">
                        <SelectField name="request_type_id" label="Type de demande" required options={requestTypes} value={data.request_type_id} onChange={set('request_type_id')} error={errors.request_type_id} />
                        <SelectField name="reception_channel" label="Canal de réception" required options={channels} value={data.reception_channel} onChange={set('reception_channel')} error={errors.reception_channel} />
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
                                onChange={set('request_type_other')}
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

                    <fieldset className={FIELDSET}>
                        <legend className={LEGEND}>
                            <UserRound className="size-4 text-brand-500 dark:text-brand-300" aria-hidden="true" /> Demandeur
                        </legend>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field name="requester_name" label="Nom du demandeur" required value={data.requester_name} onChange={set('requester_name')} error={errors.requester_name} />
                            <Field name="requester_organization" label="Organisme" required={complete} value={data.requester_organization} onChange={set('requester_organization')} error={errors.requester_organization} />
                            <Field name="requester_phone" label="Téléphone" type="tel" required={complete} value={data.requester_phone} onChange={set('requester_phone')} error={errors.requester_phone} />
                            <Field name="requester_email" label="E-mail" type="email" required={complete} value={data.requester_email} onChange={set('requester_email')} error={errors.requester_email} />
                        </div>
                    </fieldset>
                </FormPanel>

                {/* Étape 2 : réception / échéance, transmission, description */}
                <FormPanel number={2} title="Réception, transmission et description" description="Date de réception, urgence, responsables destinataires et détail de la demande." done={done2} hasError={hasError('received_at', 'urgency_level_id', 'recipients', 'description', 'attachments')}>
                    <fieldset className={FIELDSET}>
                        <legend className={LEGEND}>
                            <Clock className="size-4 text-brand-500 dark:text-brand-300" aria-hidden="true" /> Réception et échéance
                        </legend>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field name="received_at" label="Date de réception" type="date" required max={today} value={data.received_at} onChange={set('received_at')} error={errors.received_at} />
                            <SelectField name="urgency_level_id" label="Urgence" required options={urgencyLevels.map((level) => ({ value: String(level.id), label: level.label }))} value={data.urgency_level_id} onChange={set('urgency_level_id')} error={errors.urgency_level_id} />
                        </div>
                        <div className="mt-4 flex items-start gap-3 rounded-lg border border-dashed border-brand-300 bg-white px-3 py-2.5 dark:border-brand-700 dark:bg-slate-900">
                            <Calendar className="mt-0.5 size-4 shrink-0 text-brand-500 dark:text-brand-300" aria-hidden="true" />
                            <div className="text-sm">
                                <div className="text-xs font-semibold text-univ-muted dark:text-slate-400">Date d’échéance (calculée)</div>
                                <div className="font-medium" aria-live="polite" data-testid="due-preview">
                                    {preview}
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <div className="space-y-2">
                        <RecipientPicker
                            directory={directory}
                            label="Département → Responsable / Utilisateur (plusieurs possibles)"
                            selected={data.recipients}
                            onChange={(list) => {
                                form.setData('recipients', list);
                                if (list.length) setRecipientError(false);
                            }}
                            error={firstError(errors, 'recipients')}
                        />
                        <p className="flex items-center gap-1.5 text-xs text-univ-muted dark:text-slate-400">
                            <Bell className="size-3.5" aria-hidden="true" /> Chaque responsable choisi est prévenu par notification dès l’enregistrement.
                        </p>
                    </div>

                    <TextareaField name="description" label="Détail de la demande" rows={5} required={complete} placeholder="Résumé du courrier ou de l’échange, éléments attendus…" value={data.description} onChange={set('description')} error={errors.description} />

                    <FormSection title="Pièces jointes (facultatif)" Icon={Paperclip} defaultOpen={false} hasErrors={Boolean(attachmentsError(errors))}>
                        <AttachmentsField ref={files} label="Pièces jointes (courrier, CV…)" onChange={(list) => form.setData('attachments', list)} error={attachmentsError(errors)} />
                    </FormSection>
                </FormPanel>
            </FormColumns>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Confirmer l’envoi"
                question="Êtes-vous sûr de vouloir transmettre cette demande externe à"
                names={names}
                subject={data.subject.trim()}
                confirmLabel="Confirmer l’envoi"
                onConfirm={send}
                processing={form.processing}
            />
        </>
    );
}
