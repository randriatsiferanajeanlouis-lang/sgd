<x-layouts.app title="Modifier un utilisateur">
    <x-form.modal :title="$user->name" :subtitle="$user->username.' · '.$user->department?->name"
                  :action="route('admin.users.update', $user)" method="PUT" :cancel="route('admin.users.index')">

        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
            @if ($user->is_active)
                <x-badge color="green">Actif</x-badge>
            @else
                <x-badge color="red">Inactif</x-badge>
            @endif
            @if ($user->previousAccount)
                <span>Ce compte remplace l’ancien compte <strong>{{ $user->previousAccount->username }}</strong> (changement de département).</span>
            @endif
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.field name="last_name" label="Nom" :value="$user->last_name" required />
            <x-form.field name="first_name" label="Prénom" :value="$user->first_name" required />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.field name="job_title" label="Fonction" :value="$user->job_title" required />
            <x-form.select name="role_id" label="Rôle" :options="$roles->pluck('label', 'id')" :value="$user->role_id" required />
        </div>

        <x-form.section title="Coordonnées" icon="mail" :fields="['phone', 'email']">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.field name="phone" label="Téléphone" :value="$user->phone" />
                <x-form.field name="email" label="E-mail interne" type="email" :value="$user->email" />
            </div>
        </x-form.section>

        <x-slot:after>
            <x-form.section title="Compte" icon="key" :open="false">
                <div class="flex flex-wrap gap-3">
                    @can('deactivate', $user)
                        <form method="POST" action="{{ route('users.deactivate', $user) }}" onsubmit="return confirm('Désactiver ce compte ?')">
                            @csrf
                            <button type="submit" class="btn-danger">Désactiver</button>
                        </form>
                    @endcan
                    @can('reactivate', $user)
                        <form method="POST" action="{{ route('admin.users.reactivate', $user) }}">
                            @csrf
                            <button type="submit" class="btn-secondary">Réactiver</button>
                        </form>
                    @endcan
                    @can('resetPassword', $user)
                        <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" onsubmit="return confirm('Générer un nouveau mot de passe temporaire ?')">
                            @csrf
                            <button type="submit" class="btn-secondary">Réinitialiser le mot de passe</button>
                        </form>
                    @endcan
                </div>
            </x-form.section>

            @can('changeDepartment', $user)
                <x-form.section title="Changer de département" icon="building" :open="false" :fields="['department_id', 'username']">
                    <form method="POST" action="{{ route('admin.users.change-department', $user) }}" class="space-y-4" onsubmit="return confirm('L’ancien compte sera désactivé et un nouveau compte créé. Continuer ?')">
                        @csrf
                        <p class="text-xs font-medium text-slate-600 dark:text-slate-400">NB : l’ancien compte est désactivé (historique conservé) et un nouveau compte est créé.</p>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-form.select name="department_id" label="Nouveau département" :options="$departments->pluck('name', 'id')" placeholder="Sélectionner" required />
                            <x-form.field name="username" label="Nouveau nom d’utilisateur" required autocomplete="off" />
                        </div>
                        <x-form.field name="job_title" label="Nouvelle fonction" />
                        <button type="submit" class="btn-secondary">Créer le nouveau compte</button>
                    </form>
                </x-form.section>
            @endcan

            <x-form.section title="Journal du compte" icon="clock" :open="false">
                <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    @forelse ($auditLogs as $log)
                        <li class="flex flex-wrap justify-between gap-2 py-2">
                            <span>{{ __('audit.'.$log->action) }} @if ($log->comment) — {{ $log->comment }} @endif</span>
                            <span class="text-slate-500 dark:text-slate-400">{{ $log->actor?->name ?? 'Système' }} · {{ $log->created_at->format('d/m/Y H:i') }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-slate-500">Aucune action enregistrée.</li>
                    @endforelse
                </ul>
            </x-form.section>
        </x-slot:after>
    </x-form.modal>
</x-layouts.app>
