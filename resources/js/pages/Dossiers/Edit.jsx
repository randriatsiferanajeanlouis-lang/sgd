import { Head, useForm } from '@inertiajs/react';
import { Clock, FileText, Info } from 'lucide-react';
import Field from '@/components/form/Field';
import FormPage from '@/components/form/FormPage';
import FormSection from '@/components/form/FormSection';
import TextareaField from '@/components/form/TextareaField';

const SELECT = 'block w-full rounded-lg border bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-brand-500/30 dark:bg-slate-900';

/** Liste déroulante (même rendu que <x-form.select>). */
function SelectField({ name, label, value, onChange, options, placeholder = null, required = false, error }) {
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
                {placeholder !== null && <option value="">{placeholder}</option>}
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
 * Modifier un dossier (React), réservé à l'expéditeur (politique « update »).
 * Chaque modification est tracée côté Laravel (DossierEditor : ancienne et
 * nouvelle valeur) et les destinataires sont prévenus. Demande externe :
 * l'échéance reste calculée, l'urgence est obligatoire.
 */
export default function Edit({ dossier, requestTypes, urgencyLevels, otherTypeId, today, urls }) {
    const form = useForm({
        subject: dossier.subject ?? '',
        request_type_id: String(dossier.request_type_id ?? ''),
        request_type_other: dossier.request_type_other ?? '',
        urgency_level_id: String(dossier.urgency_level_id ?? (dossier.external ? (urgencyLevels[0]?.value ?? '') : '')),
        ...(dossier.external ? {} : { due_at: dossier.due_at ?? '' }),
        description: dossier.description ?? '',
        comment: '',
    });
    const { data, errors } = form;
    const isOther = otherTypeId !== null && String(data.request_type_id) === String(otherTypeId);

    const submit = (event) => {
        event.preventDefault();
        form.put(urls.update);
    };

    return (
        <>
            <Head title={`Modifier ${dossier.reference}`} />

            <FormPage
                title={`Modifier le dossier ${dossier.reference}`}
                subtitle="Chaque modification est conservée dans l’historique (ancienne et nouvelle valeur) et les destinataires sont prévenus."
                cancelUrl={urls.cancel}
                processing={form.processing}
                hasErrors={form.hasErrors}
                onSubmit={submit}
            >
                <Field name="subject" label="Objet" required maxLength={255} value={data.subject} onChange={(value) => form.setData('subject', value)} error={errors.subject} />

                <div className="grid gap-5 sm:grid-cols-2">
                    <SelectField name="request_type_id" label="Type" required options={requestTypes} value={data.request_type_id} onChange={(value) => form.setData('request_type_id', value)} error={errors.request_type_id} />
                    <SelectField
                        name="urgency_level_id"
                        label="Urgence"
                        options={urgencyLevels}
                        placeholder={dossier.external ? null : 'Non précisée'}
                        value={data.urgency_level_id}
                        onChange={(value) => form.setData('urgency_level_id', value)}
                        error={errors.urgency_level_id}
                    />
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

                <FormSection title="Échéance" Icon={Clock} hasErrors={Boolean(errors.due_at)}>
                    <div className="sm:max-w-xs">
                        {dossier.external ? (
                            <>
                                <div className="mb-1.5 text-sm font-medium text-slate-700 dark:text-slate-300">Échéance</div>
                                <div className="rounded-lg border border-dashed border-slate-300 px-3 py-2.5 text-sm dark:border-slate-700">{dossier.dueLabel} (calculée)</div>
                            </>
                        ) : (
                            <Field name="due_at" label="Date d’échéance" type="date" required min={today} value={data.due_at} onChange={(value) => form.setData('due_at', value)} error={errors.due_at} />
                        )}
                    </div>
                </FormSection>

                <FormSection title="Description" Icon={FileText} hasErrors={Boolean(errors.description)}>
                    <TextareaField name="description" label="Détail de la demande" rows={6} value={data.description} onChange={(value) => form.setData('description', value)} error={errors.description} />
                </FormSection>

                <FormSection title="Autres informations" Icon={Info} defaultOpen={false} hasErrors={Boolean(errors.comment)}>
                    <Field name="comment" label="Motif de la modification (facultatif)" value={data.comment} onChange={(value) => form.setData('comment', value)} error={errors.comment} />
                </FormSection>
            </FormPage>
        </>
    );
}
