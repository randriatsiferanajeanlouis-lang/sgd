import { Head, useForm } from '@inertiajs/react';
import { Mail, UserRound } from 'lucide-react';
import UserAvatar from '@/components/app/UserAvatar';
import Field from '@/components/form/Field';
import FormPage from '@/components/form/FormPage';
import FormSection from '@/components/form/FormSection';

const MAX_PHOTO_BYTES = 2 * 1024 * 1024;
const PHOTO_TOO_LARGE = 'La photo est trop lourde : 2 Mo maximum.';

/**
 * Modifier mes coordonnées (React). Règles inchangées côté Laravel
 * (UpdateProfileRequest) : seuls le téléphone, l'e-mail interne et la photo
 * sont modifiables ; département, rôle et fonction restent à l'administrateur.
 */
export default function Edit({ profile, urls }) {
    const form = useForm({
        phone: profile.phone ?? '',
        email: profile.email ?? '',
        photo: null,
        remove_photo: false,
    });

    const submit = (event) => {
        event.preventDefault();
        // Fichier joint : envoi multipart en POST + _method=PUT (PHP ne lit pas les fichiers d'un vrai PUT).
        form.transform((data) => ({ ...data, _method: 'put' }));
        form.post(urls.update, { forceFormData: true });
    };

    const has = (...fields) => fields.some((field) => form.errors[field]);

    return (
        <>
            <Head title="Modifier mon profil" />

            <FormPage title="Modifier mes coordonnées" cancelUrl={urls.cancel} processing={form.processing} hasErrors={form.hasErrors} onSubmit={submit}>
                <FormSection title="Coordonnées" Icon={Mail} hasErrors={has('phone', 'email')}>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field name="phone" label="Téléphone" autoComplete="tel" value={form.data.phone} onChange={(value) => form.setData('phone', value)} error={form.errors.phone} />
                        <Field
                            name="email"
                            label="E-mail interne"
                            type="email"
                            autoComplete="email"
                            value={form.data.email}
                            onChange={(value) => form.setData('email', value)}
                            error={form.errors.email}
                        />
                    </div>
                </FormSection>

                <FormSection title="Photo" Icon={UserRound} hasErrors={has('photo', 'remove_photo')}>
                    <div className="flex items-center gap-4">
                        <UserAvatar photoUrl={profile.photoUrl} initials={profile.initials} size="size-16" />
                        <div className="flex-1">
                            <Field
                                name="photo"
                                label="Nouvelle photo"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                hint="JPG, PNG ou WebP, 2 Mo maximum."
                                onChange={(file, event) => {
                                    // Vérifiée dès le choix : un fichier trop lourd n'est pas envoyé.
                                    if (file && file.size > MAX_PHOTO_BYTES) {
                                        event.target.value = '';
                                        form.setData('photo', null);
                                        form.setError('photo', PHOTO_TOO_LARGE);

                                        return;
                                    }
                                    form.clearErrors('photo');
                                    form.setData('photo', file);
                                }}
                                error={form.errors.photo}
                            />
                            {profile.photoUrl && (
                                <label className="mt-2 flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="remove_photo"
                                        value="1"
                                        checked={form.data.remove_photo}
                                        onChange={(event) => form.setData('remove_photo', event.target.checked)}
                                        className="size-4 rounded border-slate-300"
                                    />
                                    Supprimer la photo actuelle
                                </label>
                            )}
                        </div>
                    </div>
                </FormSection>
            </FormPage>
        </>
    );
}
