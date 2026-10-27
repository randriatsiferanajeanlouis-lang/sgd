import { useEffect, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import PwaInstall from '@/components/app/PwaInstall';
import Field from '@/components/form/Field';
import GuestLayout from '@/layouts/GuestLayout';

/**
 * Connexion (React). La vérification reste côté Laravel : LoginController et
 * LoginRequest (5 tentatives au plus, compte désactivé, journal d'activité).
 */
export default function Login({ loginUrl }) {
    const form = useForm({ username: '', password: '', remember: false });
    const [showPassword, setShowPassword] = useState(false);
    // Erreurs reçues avec la page elle-même (ex. compte désactivé pendant la session).
    const pageErrors = usePage().props.errors ?? {};

    // Retour arrière depuis l'application : Inertia restaurerait le formulaire
    // depuis l'historique ; on redemande la page au serveur, qui renvoie
    // l'utilisateur déjà connecté vers son tableau de bord.
    useEffect(() => {
        const reload = () => window.location.replace(window.location.href);
        // Page restaurée telle quelle par le cache « précédent / suivant » du navigateur.
        const onPageShow = (event) => event.persisted && reload();

        if (performance.getEntriesByType('navigation')[0]?.type === 'back_forward') {
            reload();
        }
        window.addEventListener('pageshow', onPageShow);

        return () => window.removeEventListener('pageshow', onPageShow);
    }, []);

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
                    error={form.errors.username ?? pageErrors.username}
                />
                <Field
                    name="password"
                    label="Mot de passe"
                    type={showPassword ? 'text' : 'password'}
                    autoComplete="current-password"
                    required
                    value={form.data.password}
                    onChange={(value) => form.setData('password', value)}
                    error={form.errors.password ?? pageErrors.password}
                    trailing={
                        <button
                            type="button"
                            onClick={() => setShowPassword((shown) => !shown)}
                            aria-label={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                            aria-pressed={showPassword}
                            title={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                            className="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-slate-500 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand-500 dark:text-slate-400 dark:hover:text-slate-200"
                        >
                            {showPassword ? <EyeOff className="size-5" aria-hidden="true" /> : <Eye className="size-5" aria-hidden="true" />}
                        </button>
                    }
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
