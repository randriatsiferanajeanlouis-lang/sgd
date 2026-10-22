import { Head, useForm } from '@inertiajs/react';
import PwaInstall from '@/components/app/PwaInstall';
import Field from '@/components/form/Field';
import GuestLayout from '@/layouts/GuestLayout';

/**
 * Connexion (React). La vérification reste côté Laravel : LoginController et
 * LoginRequest (5 tentatives au plus, compte désactivé, journal d'activité).
 */
export default function Login({ loginUrl }) {
    const form = useForm({ username: '', password: '', remember: false });

    const submit = (event) => {
        event.preventDefault();
        form.post(loginUrl, { onFinish: () => form.reset('password') });
    };

    return (
        <GuestLayout>
            <Head title="Connexion" />

            <p className="kicker mb-2">Espace sécurisé</p>
            <h2 className="text-2xl font-extrabold tracking-[-0.025em]">Connexion</h2>
            <p className="mt-1 mb-8 text-univ-muted dark:text-slate-400">Accédez à votre espace de gestion.</p>

            <form onSubmit={submit} className="space-y-5" noValidate={false}>
                <Field
                    name="username"
                    label="Identifiant"
                    autoComplete="username"
                    required
                    autoFocus
                    value={form.data.username}
                    onChange={(value) => form.setData('username', value)}
                    error={form.errors.username}
                />
                <Field
                    name="password"
                    label="Mot de passe"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={form.data.password}
                    onChange={(value) => form.setData('password', value)}
                    error={form.errors.password}
                />

                <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                    <input
                        type="checkbox"
                        name="remember"
                        checked={form.data.remember}
                        onChange={(event) => form.setData('remember', event.target.checked)}
                        className="size-4 rounded border-slate-300 text-brand-600"
                    />
                    Se souvenir de moi
                </label>

                <button type="submit" disabled={form.processing} className="btn-primary min-h-12 w-full text-[15px]">
                    {form.processing ? 'Connexion…' : 'Se connecter'}
                </button>
            </form>

            <PwaInstall variant="full" className="mt-4" />

            <p className="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">
                Mot de passe oublié ou pas encore de compte ? Adressez-vous à l’administrateur.
            </p>
        </GuestLayout>
    );
}
