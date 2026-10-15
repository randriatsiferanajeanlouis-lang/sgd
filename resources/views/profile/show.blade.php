<x-layouts.app title="Mon profil">
    <div class="mx-auto max-w-3xl">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Mon profil</h1>
            <a href="{{ route('profile.edit') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Modifier mes coordonnées</a>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center gap-4">
                <x-avatar :user="$user" size="size-20" class="text-2xl" />
                <div>
                    <div class="text-xl font-semibold">{{ $user->first_name }} {{ $user->last_name }}</div>
                    <div class="text-slate-500 dark:text-slate-400">{{ $user->username }}</div>
                </div>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    'Nom' => $user->last_name,
                    'Prénom' => $user->first_name,
                    'Fonction' => $user->job_title,
                    'Département' => $user->department?->name,
                    'Rôle' => $user->role->label,
                    'Téléphone' => $user->phone,
                    'E-mail interne' => $user->email,
                ] as $label => $value)
                    <div>
                        <dt class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                        <dd class="mt-0.5 font-medium">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>

            <p class="mt-6 text-sm text-slate-500 dark:text-slate-400">
                Le département, le rôle et la fonction sont gérés par l’administrateur.
            </p>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950">Déconnexion</button>
        </form>
    </div>
</x-layouts.app>
