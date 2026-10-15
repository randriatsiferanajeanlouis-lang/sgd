<x-layouts.app title="Départements">
    <x-page-header title="Départements" subtitle="Un département désactivé conserve ses employés, dossiers, documents et historique.">
        <x-slot:actions>
            @can('create', App\Models\Department::class)
                <a href="{{ route('admin.departments.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Ajout département</a>
            @endcan
            @cannot('create', App\Models\Department::class)
                <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <x-icon name="eye" class="size-3.5" /> Lecture seule
                </span>
            @endcannot
        </x-slot:actions>
    </x-page-header>

    {{-- Totaux --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        @canany(['manage_users'])
            <a href="{{ route('admin.users.index', ['status' => 'active']) }}" class="stat-tile bg-emerald-50 dark:bg-emerald-500/10">
        @else
            <div class="stat-tile cursor-default bg-emerald-50 hover:translate-y-0 hover:shadow-none dark:bg-emerald-500/10">
        @endcanany
                <span class="inline-flex size-9 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg shadow-emerald-500/40"><x-icon name="check" class="size-5" /></span>
                <span class="text-xs font-medium text-emerald-600 dark:text-emerald-300">Comptes actifs</span>
                <span class="text-xl font-bold tabular-nums text-emerald-600 dark:text-emerald-300" data-testid="total-active">{{ $totals['active'] }}</span>
        @canany(['manage_users'])
            </a>
        @else
            </div>
        @endcanany
        @can('manage_users')
            <a href="{{ route('admin.users.index') }}" class="stat-tile bg-brand-50 dark:bg-brand-500/10">
        @else
            <div class="stat-tile cursor-default bg-brand-50 hover:translate-y-0 hover:shadow-none dark:bg-brand-500/10">
        @endcan
                <span class="inline-flex size-9 items-center justify-center rounded-full bg-brand-500 text-white shadow-lg shadow-brand-500/40"><x-icon name="users" class="size-5" /></span>
                <span class="text-xs font-medium text-brand-600 dark:text-brand-300">Utilisateurs (tous comptes)</span>
                <span class="text-xl font-bold tabular-nums text-brand-600 dark:text-brand-300">{{ $totals['users'] }}</span>
        @can('manage_users')
            </a>
        @else
            </div>
        @endcan
        <div class="stat-tile bg-amber-50 hover:translate-y-0 hover:shadow-none dark:bg-amber-500/10">
            <span class="inline-flex size-9 items-center justify-center rounded-full bg-amber-500 text-white shadow-lg shadow-amber-500/40"><x-icon name="building" class="size-5" /></span>
            <span class="text-xs font-medium text-amber-600 dark:text-amber-300">Départements actifs</span>
            <span class="text-xl font-bold tabular-nums text-amber-600 dark:text-amber-300">{{ $totals['departments'] }}</span>
        </div>
    </div>

    {{-- Cartes par département --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($cards as $card)
            @can('manage_users')
                <a href="{{ route('admin.users.index', ['department' => $card->id]) }}"
                   title="Voir les utilisateurs du département {{ $card->name }}"
                   @class(['card group flex flex-col gap-0.5 p-3 transition hover:-translate-y-0.5 hover:border-brand-300 dark:hover:border-brand-700', 'opacity-60' => ! $card->is_active])>
            @else
                <div @class(['card flex flex-col gap-0.5 p-3', 'opacity-60' => ! $card->is_active])>
            @endcan
                <span class="truncate text-xs font-medium text-slate-600 dark:text-slate-300">{{ $card->name }}</span>
                <span class="text-xl font-bold tabular-nums text-slate-800 @can('manage_users') group-hover:text-brand-600 @endcan dark:text-white">{{ $card->users_count }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">utilisateur(s) · <span class="font-medium text-emerald-600 dark:text-emerald-400">{{ $card->active_users_count }} actif(s)</span></span>
            @can('manage_users')
                </a>
            @else
                </div>
            @endcan
        @endforeach
    </div>

    {{-- Recherche (propositions instantanées, comme sur les autres listes) --}}
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-80">
            <x-form.search-field label="Rechercher" :value="request('search')" placeholder="Nom ou code du département" />
        </div>
        <button type="submit" class="btn-secondary py-2.5">Filtrer</button>
        @if (request()->filled('search'))
            <a href="{{ route('admin.departments.index') }}" class="btn-secondary py-2.5">Effacer</a>
        @endif
    </form>

    <div class="card overflow-x-auto p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Responsable</th>
                    <th>Employés actifs</th>
                    <th>Dossiers</th>
                    @cannot('create', App\Models\Department::class)
                        <th>Mes envois</th>
                    @endcannot
                    <th>Statut</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($departments as $department)
                    <tr>
                        <td data-label="Nom" class="font-medium">{{ $department->name }} <span class="ml-1 font-mono text-xs text-slate-400">{{ $department->code }}</span></td>
                        <td data-label="Responsable">{{ $department->manager?->name ?? '—' }}</td>
                        <td data-label="Employés actifs">
                            @can('manage_users')
                                <a href="{{ route('admin.users.index', ['department' => $department->id]) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-300"
                                   title="Voir les utilisateurs du département">{{ $department->active_users_count }}</a>
                            @else
                                <span class="font-medium">{{ $department->active_users_count }}</span>
                            @endcan
                        </td>
                        <td data-label="Dossiers">{{ $department->dossiers_count }}</td>
                        @cannot('create', App\Models\Department::class)
                            <td data-label="Mes envois">
                                <a href="{{ route('directory.dossiers', $department) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-300"
                                   title="Voir les dossiers que j’ai envoyés à {{ $department->name }}">{{ (int) ($sentCounts[$department->id] ?? 0) }} dossier(s)</a>
                            </td>
                        @endcannot
                        <td data-label="Statut">
                            @if ($department->is_active)
                                <x-badge color="green">Actif</x-badge>
                            @else
                                <x-badge color="red">Désactivé</x-badge>
                            @endif
                        </td>
                        <td>
                            @can('update', $department)
                                <div class="flex flex-wrap justify-end gap-2 sm:flex-nowrap">
                                    <a href="{{ route('admin.departments.edit', $department) }}" class="btn-secondary p-2"
                                       title="Modifier / Responsable" aria-label="Modifier / Responsable">
                                        <x-icon name="pencil" class="size-4" />
                                    </a>
                                    <form method="POST" action="{{ route('admin.departments.toggle', $department) }}"
                                          @if ($department->is_active) onsubmit="return confirm('Désactiver ce département ?')" @endif>
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" @class(['p-2', 'btn-danger' => $department->is_active, 'btn-success' => ! $department->is_active])
                                                title="{{ $department->is_active ? 'Désactiver' : 'Réactiver' }}"
                                                aria-label="{{ $department->is_active ? 'Désactiver' : 'Réactiver' }}">
                                            <x-icon :name="$department->is_active ? 'ban' : 'refresh'" class="size-4" />
                                        </button>
                                    </form>
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"class="py-8 text-center text-slate-500">Aucun département ne correspond à « {{ request('search') }} ».</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
