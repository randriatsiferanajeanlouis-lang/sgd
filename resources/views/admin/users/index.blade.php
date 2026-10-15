<x-layouts.app title="Utilisateurs">
    <x-page-header title="Utilisateurs" subtitle="Comptes du personnel. Un compte n’est jamais supprimé : il est désactivé.">
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="btn-primary">Créer un utilisateur</a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="card mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
        <div class="lg:col-span-2">
            <x-form.search-field label="Recherche" :value="request('search')" placeholder="Nom, prénom ou nom d’utilisateur" />
        </div>
        <x-form.select name="department" label="Département" :options="$departments->pluck('name', 'id')" :value="request('department')" placeholder="Tous" />
        <x-form.select name="role" label="Rôle" :options="$roles->pluck('label', 'id')" :value="request('role')" placeholder="Tous" />
        <div class="flex gap-2">
            <div class="flex-1">
                <x-form.select name="status" label="Statut" :options="['active' => 'Actifs', 'inactive' => 'Inactifs']" :value="request('status')" placeholder="Tous" />
            </div>
            <button type="submit" class="btn-secondary self-end py-2.5">Filtrer</button>
        </div>
    </form>

    <div class="card overflow-x-auto p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Username</th>
                    <th>Département</th>
                    <th>Fonction</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td data-label="Nom" class="font-medium">{{ $user->last_name }}</td>
                        <td data-label="Prénom">{{ $user->first_name }}</td>
                        <td data-label="Username" class="font-mono text-xs">{{ $user->username }}</td>
                        <td data-label="Département">{{ $user->department?->name ?? 'Super administrateur (aucun)' }}</td>
                        <td data-label="Fonction">{{ $user->job_title }}</td>
                        <td data-label="Rôle">{{ $user->role->label }}</td>
                        <td data-label="Statut">
                            @if ($user->is_active)
                                <x-badge color="green">Actif</x-badge>
                            @else
                                <x-badge color="red">Inactif</x-badge>
                            @endif
                        </td>
                        <td>
                            <div class="flex flex-wrap justify-end gap-2 sm:flex-nowrap">
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn-secondary p-2" title="Modifier" aria-label="Modifier">
                                    <x-icon name="pencil" class="size-4" />
                                </a>
                                @can('deactivate', $user)
                                    <form method="POST" action="{{ route('users.deactivate', $user) }}" onsubmit="return confirm('Désactiver ce compte ?')">
                                        @csrf
                                        <button type="submit" class="btn-danger p-2" title="Désactiver" aria-label="Désactiver">
                                            <x-icon name="ban" class="size-4" />
                                        </button>
                                    </form>
                                @endcan
                                @can('reactivate', $user)
                                    <form method="POST" action="{{ route('admin.users.reactivate', $user) }}">
                                        @csrf
                                        <button type="submit" class="btn-success p-2" title="Réactiver" aria-label="Réactiver">
                                            <x-icon name="refresh" class="size-4" />
                                        </button>
                                    </form>
                                @endcan
                                @can('resetPassword', $user)
                                    <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" onsubmit="return confirm('Générer un nouveau mot de passe temporaire ?')">
                                        @csrf
                                        <button type="submit" class="btn-secondary p-2" title="Réinitialiser le mot de passe" aria-label="Réinitialiser le mot de passe">
                                            <x-icon name="key" class="size-4" />
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-slate-500">Aucun utilisateur ne correspond à ces critères.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.app>
