import { Head, useForm } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import Field from '@/components/form/Field';
import FormPage from '@/components/form/FormPage';
import FormSection from '@/components/form/FormSection';

const EMPTY = { current_password: '', password: '', password_confirmation: '' };

/**
 * Changer mon mot de passe (React). Les règles restent côté Laravel :
 * ChangePasswordRequest (mot de passe actuel exact, 8 caractères avec
 * majuscule, minuscule et chiffre, confirmation, différent de l'actuel).
 * Mot de passe temporaire : la page est imposée et ne propose pas d'annuler.
 */
export default function ChangePassword({ isMandatory, urls }) {
    const form = useForm(EMPTY);

    const submit = (event) => {
        event.preventDefault();
        // Comme avant : les mots de passe ne sont jamais réaffichés après un envoi.
        form.put(urls.update, { onFinish: () => form.reset() });
    };

    const field = (name) => ({
        name,
        type: 'password',
        required: true,
        value: form.data[name],
        onChange: (value) => form.setData(name, value),
        error: form.errors[name],
    });

    return (
        <>
            <Head title="Mot de passe" />

            <FormPage
                title={isMandatory ? 'Remplacez votre mot de passe temporaire' : 'Changer mon mot de passe'}
                cancelUrl={isMandatory ? null : urls.cancel}
                processing={form.processing}
                hasErrors={form.hasErrors}
                onSubmit={submit}
            >
                <Field label="Mot de passe actuel" autoComplete="current-password" {...field('current_password')} />

                <FormSection title="Nouveau mot de passe" Icon={KeyRound} hasErrors={Boolean(form.errors.password || form.errors.password_confirmation)}>
                    <Field
                        label="Nouveau mot de passe"
                        autoComplete="new-password"
                        hint="Au moins 8 caractères, avec une majuscule, une minuscule et un chiffre."
                        {...field('password')}
                    />
                    <Field label="Confirmation du nouveau mot de passe" autoComplete="new-password" {...field('password_confirmation')} />
                </FormSection>
            </FormPage>
        </>
    );
}
