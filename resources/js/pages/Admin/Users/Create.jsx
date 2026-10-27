import { Head, useForm } from '@inertiajs/react';
import { Building2, Info, KeyRound, Plus } from 'lucide-react';
import Field from '@/components/form/Field';
import FormPage from '@/components/form/FormPage';
import FormSection from '@/components/form/FormSection';

const MAX_PHOTO_BYTES = 2 * 1024 * 1024;

/** Liste déroulante (même rendu que <x-form.select>). */
function SelectField({ name, label, value, onChange, options, required = false, error }) {
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
            <select
                id={name}
                name={name}
                required={required}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className={`block w-full rounded-lg border bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-brand-500/30 dark:bg-slate-900 ${error ? 'border-red-400' : 'border-slate-300 focus:border-brand-500 dark:border-slate-700'}`}
            >
                <option value="">Sélectionner</option>
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
 * Créer un utilisateur (React), réservé à l'administrateur. Règles côté
 * Laravel (StoreUserRequest) ; l'utilisateur devra changer son mot de passe
 * à la première connexion.
 */
export default function Create({ departments, roles, urls }) {
    const form = useForm({
        last_name: '',
        first_name: '',
        department_id: '',
        job_title: '',
        role_id: '',
        username: '',
        password: '',
        password_confirmation: '',
        is_active: true,
        phone: '',
        email: '',
        photo: null,
    });
    const { data, errors } = form;
    const set = (name) => (value) => form.setData(name, value);
    const has = (...names) => names.some((name) => errors[name]);

    const submit = (event) => {
        event.preventDefault();
        form.post(urls.store, { forceFormData: true, onError: () => form.reset('password', 'password_confirmation') });
    };

    return (
        <>
            <Head title="Créer un utilisateur" />

            <FormPage
                title="Créer un utilisateur"
                subtitle="L’utilisateur devra changer ce mot de passe à sa première connexion."
                cancelUrl={urls.cancel}
                submitLabel="Créer le compte"
                SubmitIcon={Plus}
                processing={form.processing}
                hasErrors={form.hasErrors}
                onSubmit={submit}
            >
                <div className="grid gap-5 sm:grid-cols-2">
                    <Field name="last_name" label="Nom" required value={data.last_name} onChange={set('last_name')} error={errors.last_name} />
                    <Field name="first_name" label="Prénom" required value={data.first_name} onChange={set('first_name')} error={errors.first_name} />
                </div>

                <FormSection title="Poste" Icon={Building2} hasErrors={has('department_id', 'job_title', 'role_id')}>
                    <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        <SelectField name="department_id" label="Département" required options={departments} value={data.department_id} onChange={set('department_id')} error={errors.department_id} />
                        <Field name="job_title" label="Fonction" required value={data.job_title} onChange={set('job_title')} error={errors.job_title} />
                        <SelectField name="role_id" label="Rôle" required options={roles} value={data.role_id} onChange={set('role_id')} error={errors.role_id} />
                    </div>
                </FormSection>

                <FormSection title="Connexion" Icon={KeyRound} hasErrors={has('username', 'password', 'password_confirmation', 'is_active')}>
                    <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        <Field name="username" label="Nom d’utilisateur" required autoComplete="off" hint="Minuscules, chiffres, . _ -" value={data.username} onChange={set('username')} error={errors.username} />
                        <Field
                            name="password"
                            label="Mot de passe"
                            type="password"
                            required
                            autoComplete="new-password"
                            hint="Au moins 8 caractères, avec une majuscule, une minuscule et un chiffre."
                            value={data.password}
                            onChange={set('password')}
                            error={errors.password}
                        />
                        <Field name="password_confirmation" label="Confirmation du mot de passe" type="password" required autoComplete="new-password" value={data.password_confirmation} onChange={set('password_confirmation')} error={errors.password_confirmation} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" checked={data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} className="size-4 rounded border-slate-300" />
                        Compte actif
                    </label>
                </FormSection>

                <FormSection title="Autres informations" Icon={Info} defaultOpen={false} hasErrors={has('phone', 'email', 'photo')}>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field name="phone" label="Téléphone" value={data.phone} onChange={set('phone')} error={errors.phone} />
                        <Field name="email" label="E-mail interne" type="email" value={data.email} onChange={set('email')} error={errors.email} />
                    </div>
                    <Field
                        name="photo"
                        label="Photo"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        onChange={(file, event) => {
                            // Même limite que la page « Modifier mes coordonnées » : 2 Mo.
                            if (file && file.size > MAX_PHOTO_BYTES) {
                                event.target.value = '';
                                form.setData('photo', null);
                                form.setError('photo', 'La photo est trop lourde : 2 Mo maximum.');
                                return;
                            }
                            form.clearErrors('photo');
                            form.setData('photo', file);
                        }}
                        error={errors.photo}
                    />
                </FormSection>
            </FormPage>
        </>
    );
}
