import { Head } from '@inertiajs/react';
import UserAvatar from '@/components/app/UserAvatar';

/**
 * Mon profil (React) : identité et rattachement de l'utilisateur connecté.
 * Les données viennent de ProfileController@show ; la modification se fait
 * sur la page « Modifier mes coordonnées ».
 */
export default function Show({ profile, urls }) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    return (
        <>
            <Head title="Mon profil" />

            <div className="mx-auto max-w-3xl">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-2xl font-semibold">Mon profil</h1>
                    <a href={urls.edit} className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                        Modifier mes coordonnées
                    </a>
                </div>

                <div className="mt-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center gap-4">
                        <UserAvatar photoUrl={profile.photoUrl} initials={profile.initials} size="size-20" className="text-2xl" />
                        <div>
                            <div className="text-xl font-semibold">
                                {profile.firstName} {profile.lastName}
                            </div>
                            <div className="text-slate-500 dark:text-slate-400">{profile.username}</div>
                        </div>
                    </div>

                    <dl className="mt-6 grid gap-4 sm:grid-cols-2">
                        {profile.details.map(({ label, value }) => (
                            <div key={label}>
                                <dt className="text-sm text-slate-500 dark:text-slate-400">{label}</dt>
                                <dd className="mt-0.5 font-medium">{value || '—'}</dd>
                            </div>
                        ))}
                    </dl>

                    <p className="mt-6 text-sm text-slate-500 dark:text-slate-400">Le département, le rôle et la fonction sont gérés par l’administrateur.</p>
                </div>

                {/* Vrai formulaire : la déconnexion recharge entièrement la page (retour à la connexion). */}
                <form method="POST" action={urls.logout} className="mt-6">
                    <input type="hidden" name="_token" value={csrf} />
                    <button
                        type="submit"
                        className="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950"
                    >
                        Déconnexion
                    </button>
                </form>
            </div>
        </>
    );
}
