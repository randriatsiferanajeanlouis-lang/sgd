<x-layouts.guest title="Connexion">
    <p class="kicker mb-2">Espace sécurisé</p>
    <h2 class="text-2xl font-extrabold tracking-[-0.025em]">Connexion</h2>
    <p class="mt-1 mb-8 text-univ-muted dark:text-slate-400">Accédez à votre espace de gestion.</p>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf

        <x-form.field name="username" label="Identifiant" autocomplete="username" required autofocus />
        <x-form.field name="password" label="Mot de passe" type="password" autocomplete="current-password" required />

        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
            <input type="checkbox" name="remember" class="size-4 rounded border-slate-300 text-brand-600">
            Se souvenir de moi
        </label>

        <button type="submit" class="btn-primary min-h-12 w-full text-[15px]">
            Se connecter
        </button>
    </form>

    <x-pwa-install variant="full" class="mt-4" />

    <p class="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">
        Mot de passe oublié ou pas encore de compte ? Adressez-vous à l’administrateur.
    </p>
</x-layouts.guest>
