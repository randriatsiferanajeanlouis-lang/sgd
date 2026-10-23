import { Head, useForm } from '@inertiajs/react';
import { FileText, UserRound } from 'lucide-react';
import Field from '@/components/form/Field';
import FormPage from '@/components/form/FormPage';
import FormSection from '@/components/form/FormSection';
import TextareaField from '@/components/form/TextareaField';

/**
 * Créer ou modifier un département (React), réservé à l'administrateur.
 * Règles côté Laravel (SaveDepartmentRequest : nom et code uniques, code
 * normalisé en majuscules ; responsable = compte actif du département).
 */
export default function Form({ department, members, urls }) {
    const editing = department.id !== null;
    const form = useForm({
        name: department.name ?? '',
        code: department.code ?? '',
        email: department.email ?? '',
        ...(editing ? { manager_id: department.manager_id ? String(department.manager_id) : '' } : {}),
        description: department.description ?? '',
    });
    const { data, errors } = form;

    const submit = (event) => {
        event.preventDefault();
        editing ? form.put(urls.save) : form.post(urls.save);
    };

    return (
        <>
            <Head title={editing ? 'Modifier un département' : 'Créer un département'} />

            <FormPage
                title={editing ? `Modifier le département ${department.name}` : 'Créer un département'}
                cancelUrl={urls.cancel}
                processing={form.processing}
                hasErrors={form.hasErrors}
                onSubmit={submit}
            >
                <Field name="name" label="Nom" required value={data.name} onChange={(value) => form.setData('name', value)} error={errors.name} />

                <div className="grid gap-5 sm:grid-cols-2">
                    <Field name="code" label="Code" required hint="Identifiant court, ex. PRODUCTION" value={data.code} onChange={(value) => form.setData('code', value)} error={errors.code} />
                    <Field name="email" label="E-mail du département" type="email" value={data.email} onChange={(value) => form.setData('email', value)} error={errors.email} />
                </div>

                {editing && (
                    <FormSection title="Responsable" Icon={UserRound} hasErrors={Boolean(errors.manager_id)}>
                        <div>
                            <label htmlFor="manager_id" className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                Responsable
                            </label>
                            <select
                                id="manager_id"
                                name="manager_id"
                                value={data.manager_id}
                                onChange={(event) => form.setData('manager_id', event.target.value)}
                                className={`block w-full rounded-lg border bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-brand-500/30 dark:bg-slate-900 ${errors.manager_id ? 'border-red-400' : 'border-slate-300 focus:border-brand-500 dark:border-slate-700'}`}
                            >
                                <option value="">Aucun responsable</option>
                                {members.map((member) => (
                                    <option key={member.value} value={member.value}>
                                        {member.label}
                                    </option>
                                ))}
                            </select>
                            {errors.manager_id && <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{errors.manager_id}</p>}
                        </div>
                        <p className="text-xs font-medium text-slate-600 dark:text-slate-400">NB : le responsable choisi reçoit le rôle Responsable s’il était Employé.</p>
                    </FormSection>
                )}

                <FormSection title="Description" Icon={FileText} defaultOpen={Boolean(department.description)} hasErrors={Boolean(errors.description)}>
                    <TextareaField name="description" label="Présentation du département" rows={3} value={data.description} onChange={(value) => form.setData('description', value)} error={errors.description} />
                </FormSection>
            </FormPage>
        </>
    );
}
