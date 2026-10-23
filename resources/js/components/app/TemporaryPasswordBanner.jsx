import { usePage } from '@inertiajs/react';

/**
 * Mot de passe temporaire généré par l'administrateur (même bandeau que
 * <x-flash> côté Blade) : affiché une seule fois, juste après l'action.
 */
export default function TemporaryPasswordBanner() {
    const temporary = usePage().props.flash?.temporaryPassword;

    if (!temporary) {
        return null;
    }

    return (
        <div className="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100" role="alert" data-testid="temporary-password">
            Mot de passe temporaire de <strong>{temporary.username}</strong> : <code className="rounded bg-white px-2 py-0.5 font-mono select-all dark:bg-slate-900">{temporary.password}</code> — affiché une seule fois.
            L’utilisateur devra le changer à sa prochaine connexion.
        </div>
    );
}
