<x-layouts.app title="Employés">
    @php
        $viewer = auth()->user();
        $all = $members->getCollection();
        $inactiveCount = $summary['members'] - $summary['active'];
        $activeRate = $summary['members'] > 0 ? (int) round($summary['active'] / $summary['members'] * 100) : 0;
        $attention = $all->where('is_active', true)->sortByDesc('overdue_count')->first(fn ($member) => $member->overdue_count > 0);
        $nextDue = $summary['next_due'] ? \Illuminate\Support\Carbon::parse($summary['next_due'])->locale('fr') : null;
    @endphp

    {{-- Fil d'Ariane --}}
    <nav class="mb-4 flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400" aria-label="Fil d’Ariane">
        <a href="{{ route('dashboard') }}" class="hover:text-slate-800 dark:hover:text-white">Espace responsable</a>
        <span aria-hidden="true">›</span>
        <span class="font-medium text-slate-800 dark:text-white">Employés</span>
    </nav>

    {{-- En-tête du département --}}
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex min-w-0 items-center gap-4">
            <span class="inline-flex size-14 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-brand-400 to-violet-500 text-lg font-bold text-white shadow-lg shadow-brand-500/30" aria-hidden="true">
                {{ mb_strtoupper(mb_substr($department?->name ?? '?', 0, 2)) }}
            </span>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ $department?->name ?? 'Département' }}</h1>
                    @if ($summary['overdue'] > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                            <span class="size-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>{{ $summary['overdue'] }} en retard
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                            <span class="size-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>À jour
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Employés du département · {{ $summary['members'] }} membre(s)
                    @if ($manager) · Responsable : {{ $manager->name }} @endif
                    @if ($department?->code) · {{ $department->code }} @endif
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if (Route::has('dossiers.department') && $viewer->hasPermission('view_department_dossiers'))
                <a href="{{ route('dossiers.department') }}" class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                    <x-icon name="inbox" class="size-4" /> Dossiers du département
                </a>
            @endif
            @if (Route::has('statistics.index') && ($viewer->hasPermission('view_department_statistics') || $viewer->hasPermission('view_global_statistics')))
                <a href="{{ route('statistics.index') }}" class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100">
                    <x-icon name="chart" class="size-4" /> Statistiques
                </a>
            @endif
        </div>
    </header>

    {{-- Bandeau d'indicateurs --}}
    <dl class="mt-6 grid overflow-hidden rounded-xl border border-slate-200 bg-white sm:grid-cols-2 lg:grid-cols-4 dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-5 py-4 sm:border-r lg:border-b-0 dark:border-slate-800">
            <dt class="text-xs text-slate-500 dark:text-slate-400">Membres</dt>
            <dd class="mt-1.5 flex items-center gap-3">
                <span class="font-semibold">{{ $summary['active'] }} actif(s)</span>
                <span class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"><span class="block h-full rounded-full bg-brand-500" style="width: {{ $activeRate }}%"></span></span>
            </dd>
        </div>
        <div class="border-b border-slate-200 px-5 py-4 lg:border-r lg:border-b-0 dark:border-slate-800">
            <dt class="text-xs text-slate-500 dark:text-slate-400">Traitements ouverts</dt>
            <dd class="mt-1.5 flex items-center gap-2">
                <span class="font-semibold">{{ $summary['open'] }} en cours</span>
                @if ($summary['overdue'] > 0)
                    <span class="rounded-md bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-500/15 dark:text-red-300">{{ $summary['overdue'] }} en retard</span>
                @endif
            </dd>
        </div>
        <div class="border-b border-slate-200 px-5 py-4 sm:border-r sm:border-b-0 dark:border-slate-800">
            <dt class="text-xs text-slate-500 dark:text-slate-400">Taux de réponse</dt>
            <dd class="mt-1.5 flex items-center gap-3">
                <span class="font-semibold">{{ $summary['answer_rate'] !== null ? $summary['answer_rate'].' %' : '—' }}</span>
                <span class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"><span class="block h-full rounded-full bg-brand-500" style="width: {{ $summary['answer_rate'] ?? 0 }}%"></span></span>
            </dd>
        </div>
        <div class="px-5 py-4">
            <dt class="text-xs text-slate-500 dark:text-slate-400">Prochaine échéance</dt>
            <dd class="mt-1.5 font-semibold">
                @if ($nextDue)
                    {{ $nextDue->isToday() ? 'Aujourd’hui '.$nextDue->format('H:i') : $nextDue->isoFormat('ddd D MMM, HH:mm') }}
                @else
                    —
                @endif
            </dd>
        </div>
    </dl>

    <div x-data="{ tab: 'all', closing: null }">
        {{-- Onglets --}}
        <div class="mt-6 flex gap-6 border-b border-slate-200 text-sm dark:border-slate-800" role="tablist">
            @foreach (['all' => ['Tous', $summary['members']], 'active' => ['Actifs', $summary['active']], 'inactive' => ['Désactivés', $inactiveCount]] as $key => [$label, $count])
                <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="(tab === '{{ $key }}').toString()"
                        :class="tab === '{{ $key }}' ? 'border-brand-500 text-slate-900 dark:text-white' : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white'"
                        class="-mb-px flex items-center gap-2 border-b-2 pb-3 font-medium transition">
                    {{ $label }}
                    <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-slate-100 px-1.5 text-xs tabular-nums text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $count }}</span>
                </button>
            @endforeach
        </div>

        <div class="mt-6 grid items-start gap-6 lg:grid-cols-3">
            {{-- Liste de l'équipe --}}
            <section class="card p-0 lg:col-span-2">
                <div class="flex flex-wrap items-center gap-2 px-5 pt-5 pb-3">
                    <x-icon name="users" class="size-5 text-slate-500 dark:text-slate-400" />
                    <h2 class="font-semibold">Équipe</h2>
                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ $summary['active'] }} actif(s) · {{ $inactiveCount }} désactivé(s)</span>
                </div>

                <ul class="divide-y divide-slate-100 px-2 pb-2 dark:divide-slate-800">
                    @forelse ($members as $member)
                        @php
                            [$dot, $state, $pill] = match (true) {
                                ! $member->is_active => ['border border-slate-400 bg-transparent', 'Désactivé', 'text-slate-500 dark:text-slate-400'],
                                $member->overdue_count > 0 => ['bg-red-500', 'En retard', 'text-slate-700 dark:text-slate-200'],
                                $member->open_count > 0 => ['bg-brand-500', 'En cours', 'text-slate-700 dark:text-slate-200'],
                                default => ['bg-emerald-500', 'À jour', 'text-slate-700 dark:text-slate-200'],
                            };
                        @endphp
                        <li x-show="tab === 'all' || tab === '{{ $member->is_active ? 'active' : 'inactive' }}'"
                            @class(['rounded-lg px-3 py-3.5', 'bg-red-50 dark:bg-red-500/10' => $member->is_active && $member->overdue_count > 0])>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                                <x-avatar :user="$member" size="size-9" :class="$member->is_active ? '' : 'opacity-50'" />

                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium">{{ $member->last_name }} {{ $member->first_name }}</div>
                                    <div class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $member->job_title ?: '—' }} · {{ $member->role?->label }}</div>
                                </div>

                                <div class="flex flex-wrap items-center gap-3 text-sm">
                                    @if ($member->overdue_count > 0)
                                        <span class="font-medium text-red-600 dark:text-red-400">{{ $member->overdue_count }} en retard</span>
                                    @endif
                                    @if ($member->open_count > 0)
                                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs tabular-nums text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $member->open_count }} en cours</span>
                                    @endif
                                    <span class="hidden text-slate-500 sm:inline dark:text-slate-400">{{ $member->answered_count }} répondu(s)</span>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium dark:bg-slate-800 {{ $pill }}" data-label="Statut du compte">
                                        <span class="size-1.5 rounded-full {{ $dot }}" aria-hidden="true"></span>{{ $state }}
                                    </span>

                                    @can('deactivate', $member)
                                        <button type="button" @click="closing = closing === {{ $member->id }} ? null : {{ $member->id }}"
                                                class="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                                                :aria-expanded="(closing === {{ $member->id }}).toString()">Désactiver</button>
                                    @endcan
                                </div>
                            </div>

                            @can('deactivate', $member)
                                <form method="POST" action="{{ route('users.deactivate', $member) }}" x-show="closing === {{ $member->id }}" x-cloak
                                      class="mt-3 flex flex-wrap items-center gap-2 pl-13"
                                      onsubmit="return confirm('Désactiver le compte de {{ $member->name }} ? L’administrateur sera notifié.')">
                                    @csrf
                                    <input type="text" name="comment" placeholder="Motif (facultatif)"
                                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm sm:w-64 dark:border-slate-700 dark:bg-slate-900">
                                    <button type="submit" class="btn-danger px-3 py-1.5">Confirmer la désactivation</button>
                                    <button type="button" @click="closing = null" class="text-sm text-slate-500 hover:text-slate-800 dark:hover:text-white">Annuler</button>
                                </form>
                            @endcan
                        </li>
                    @empty
                        <li class="px-3 py-8 text-center text-slate-500">Aucun employé dans ce département.</li>
                    @endforelse
                </ul>
            </section>

            {{-- Colonne de droite --}}
            <div class="space-y-6">
                {{-- Point d'attention --}}
                <section class="relative overflow-hidden rounded-xl border border-brand-300/40 bg-linear-to-br from-white via-brand-50 to-violet-100 p-5 dark:border-brand-500/30 dark:from-slate-900 dark:via-brand-900/40 dark:to-violet-900/50">
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-brand-500/10 px-2 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">✦ Point d’attention</span>
                    @if ($attention)
                        <h2 class="mt-3 font-semibold">{{ $attention->name }} a {{ $attention->overdue_count }} traitement(s) en retard</h2>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                            Sur {{ $attention->open_count }} dossier(s) en cours. Un rappel ou une nouvelle répartition peut l’aider à rattraper son retard.
                        </p>
                    @else
                        <h2 class="mt-3 font-semibold">Aucun retard dans l’équipe</h2>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Tous les traitements ouverts sont dans les délais.</p>
                    @endif
                    @if (Route::has('dossiers.department') && $viewer->hasPermission('view_department_dossiers'))
                        <a href="{{ route('dossiers.department') }}" class="mt-4 inline-flex rounded-full bg-white px-4 py-1.5 text-sm font-medium text-slate-900 shadow-sm hover:bg-slate-100">Voir les dossiers</a>
                    @endif
                </section>

                {{-- Prochaine échéance --}}
                <section class="card p-5">
                    <div class="flex items-center gap-2">
                        <x-icon name="clock" class="size-5 text-slate-500 dark:text-slate-400" />
                        <h2 class="font-semibold">Prochaine échéance</h2>
                        @if ($summary['open'] > 0)
                            <span class="ml-auto inline-flex items-center gap-1.5 rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                <span class="size-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>Ouvert
                            </span>
                        @endif
                    </div>
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                        @if ($nextDue)
                            Le prochain traitement à rendre est attendu le <strong>{{ $nextDue->isoFormat('dddd D MMMM à HH:mm') }}</strong>.
                        @else
                            Aucun traitement ouvert à venir.
                        @endif
                    </p>
                    <p class="mt-3 rounded-lg border border-brand-300/40 bg-brand-50 px-3 py-2 text-sm text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">
                        {{ $summary['open'] }} traitement(s) ouvert(s) dans le département, dont {{ $summary['overdue'] }} en retard.
                    </p>
                </section>

                {{-- Suivi de l'équipe --}}
                <section class="card p-5">
                    <div class="flex items-center gap-2">
                        <x-icon name="chart" class="size-5 text-slate-500 dark:text-slate-400" />
                        <h2 class="font-semibold">Suivi de l’équipe</h2>
                    </div>
                    <dl class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <div class="flex items-center justify-between gap-3 py-3">
                            <dt class="text-slate-600 dark:text-slate-300">Taux de réponse</dt>
                            <dd class="flex items-center gap-3">
                                <span class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"><span class="block h-full rounded-full bg-brand-500" style="width: {{ $summary['answer_rate'] ?? 0 }}%"></span></span>
                                <span class="w-10 text-right font-semibold tabular-nums">{{ $summary['answer_rate'] !== null ? $summary['answer_rate'].'%' : '—' }}</span>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 py-3">
                            <dt class="text-slate-600 dark:text-slate-300">Comptes actifs</dt>
                            <dd class="flex items-center gap-3">
                                <span class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"><span class="block h-full rounded-full bg-brand-500" style="width: {{ $activeRate }}%"></span></span>
                                <span class="w-10 text-right font-semibold tabular-nums">{{ $activeRate }}%</span>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 py-3">
                            <dt class="text-slate-600 dark:text-slate-300">Délai moyen de réponse</dt>
                            <dd class="font-semibold tabular-nums">{{ $summary['average_hours'] !== null ? $summary['average_hours'].' h' : '—' }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </div>

    <div class="mt-4">{{ $members->links() }}</div>
</x-layouts.app>
