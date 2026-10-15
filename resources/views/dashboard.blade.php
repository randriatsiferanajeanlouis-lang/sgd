<x-layouts.app title="Tableau de bord">
    {{-- Bandeau d'accueil --}}
    <section class="relative overflow-hidden rounded-xl bg-linear-to-r from-brand-50 via-white to-white p-6 shadow-[0_2px_12px_-4px_rgb(15_23_42/0.08)] sm:p-7 dark:from-slate-800 dark:via-slate-900 dark:to-slate-900 dark:shadow-none">
        <div class="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-brand-500/10 blur-2xl dark:bg-brand-500/20" aria-hidden="true"></div>

        <div class="relative flex flex-wrap items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <x-avatar :user="$user" size="size-14" class="ring-4 ring-white dark:ring-slate-800" />
                <div>
                    <h1 class="text-xl font-semibold sm:text-2xl">Bienvenue, {{ $user->first_name ?: $user->name }} 👋</h1>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $user->department ? $user->department->name.' · ' : '' }}{{ $user->role->label }} — consultez vos dossiers</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Chiffres clés : une couleur et une icône par tuile --}}
    @isset($stats)
        @php
            $tones = [
                ['icon' => 'inbox', 'tile' => 'bg-brand-50 dark:bg-brand-500/10', 'badge' => 'bg-brand-500 shadow-brand-500/40', 'text' => 'text-brand-600 dark:text-brand-300'],
                ['icon' => 'clock', 'tile' => 'bg-red-50 dark:bg-red-500/10', 'badge' => 'bg-red-500 shadow-red-500/40', 'text' => 'text-red-600 dark:text-red-300'],
                ['icon' => 'send', 'tile' => 'bg-amber-50 dark:bg-amber-500/10', 'badge' => 'bg-amber-500 shadow-amber-500/40', 'text' => 'text-amber-600 dark:text-amber-300'],
                ['icon' => 'check', 'tile' => 'bg-emerald-50 dark:bg-emerald-500/10', 'badge' => 'bg-emerald-500 shadow-emerald-500/40', 'text' => 'text-emerald-600 dark:text-emerald-300'],
            ];
        @endphp

        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($stats as $stat)
                @php($tone = $tones[$loop->index % count($tones)])
                <a href="{{ $stat['url'] }}" class="stat-tile {{ $tone['tile'] }}">
                    <span class="inline-flex size-9 items-center justify-center rounded-full text-white shadow-lg {{ $tone['badge'] }}">
                        <x-icon :name="$tone['icon']" class="size-5" />
                    </span>
                    <span class="text-xs font-medium {{ $tone['text'] }}">{{ $stat['label'] }}</span>
                    <span class="text-xl font-bold tabular-nums {{ $tone['text'] }}">{{ $stat['value'] }}</span>
                </a>
            @endforeach
        </div>
    @endisset

    {{-- Graphiques pour tous les utilisateurs : activité personnelle et traitements par statut --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-chart.trend class="lg:col-span-2" :trends="$personalTrends" title="Mon activité"
                       subtitle="Dossiers que vous avez reçus et envoyés" testid="personal-chart" />

        <x-chart.bar title="Mes traitements" subtitle="Dossiers reçus, par état de traitement" :data="$myTreatments" />
    </div>

    {{-- Évolution des dossiers par département (responsables et statistiques générales) --}}
    @if ($trends)
        <x-chart.trend class="mt-6" :trends="$trends" title="Évolution des dossiers"
                       :subtitle="count($trends['periods']['month']['series']) === 1 ? 'Dossiers émis ou reçus par le département' : 'Dossiers émis ou reçus par chaque département'"
                       testid="trend-chart" />
    @endif

    {{-- Vue d'ensemble des départements (administrateur) --}}
    @isset($adminOverview)
        <div class="mt-10" x-data="{ tab: 'all' }">
            {{-- En-tête de section --}}
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Vue d'ensemble des départements</h2>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $adminOverview['departments']->count() }} département(s) actif(s) · {{ $adminOverview['totals']['users'] }} utilisateur(s) actif(s)</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        <x-icon name="users" class="size-4" /> Utilisateurs
                    </a>
                    @if (Route::has('statistics.index'))
                        <a href="{{ route('statistics.index') }}" class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100">
                            <x-icon name="chart" class="size-4" /> Statistiques
                        </a>
                    @endif
                </div>
            </div>

            {{-- Bandeau de totaux --}}
            <dl class="mt-4 grid overflow-hidden rounded-xl border border-slate-200 bg-white sm:grid-cols-2 lg:grid-cols-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 sm:border-r lg:border-b-0 dark:border-slate-800">
                    <dt class="text-xs text-slate-500 dark:text-slate-400">Utilisateurs actifs</dt>
                    <dd class="mt-1.5 font-semibold">{{ $adminOverview['totals']['users'] }}</dd>
                </div>
                <div class="border-b border-slate-200 px-5 py-4 lg:border-r lg:border-b-0 dark:border-slate-800">
                    <dt class="text-xs text-slate-500 dark:text-slate-400">Traitements ouverts</dt>
                    <dd class="mt-1.5 flex items-center gap-2">
                        <span class="font-semibold">{{ $adminOverview['totals']['open'] }}</span>
                        @if ($adminOverview['totals']['overdue'] > 0)
                            <span class="rounded-md bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-500/15 dark:text-red-300">{{ $adminOverview['totals']['overdue'] }} en retard</span>
                        @endif
                    </dd>
                </div>
                <div class="border-b border-slate-200 px-5 py-4 sm:border-r sm:border-b-0 dark:border-slate-800">
                    <dt class="text-xs text-slate-500 dark:text-slate-400">Taux de réponse global</dt>
                    <dd class="mt-1.5 flex items-center gap-3">
                        <span class="font-semibold">{{ $adminOverview['totals']['answer_rate'] !== null ? $adminOverview['totals']['answer_rate'].' %' : '—' }}</span>
                        <span class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"><span class="block h-full rounded-full bg-brand-500" style="width: {{ $adminOverview['totals']['answer_rate'] ?? 0 }}%"></span></span>
                    </dd>
                </div>
                <div class="px-5 py-4">
                    <dt class="text-xs text-slate-500 dark:text-slate-400">Départements actifs</dt>
                    <dd class="mt-1.5 font-semibold">{{ $adminOverview['departments']->count() }}</dd>
                </div>
            </dl>

            {{-- Onglets --}}
            <div class="mt-6 flex gap-6 border-b border-slate-200 text-sm dark:border-slate-800" role="tablist">
                @foreach (['all' => ['Tous', $adminOverview['departments']->count()], 'alert' => ['Alertes', $adminOverview['alert_count']]] as $key => [$label, $count])
                    <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="(tab === '{{ $key }}').toString()"
                            :class="tab === '{{ $key }}' ? 'border-brand-500 text-slate-900 dark:text-white' : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white'"
                            class="-mb-px flex items-center gap-2 border-b-2 pb-3 font-medium transition">
                        {{ $label }}
                        <span class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 text-xs tabular-nums {{ $key === 'alert' && $count > 0 ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $count }}</span>
                    </button>
                @endforeach
            </div>

            <div class="mt-6 grid items-start gap-6 lg:grid-cols-3">
                {{-- Liste des départements --}}
                <section class="card p-0 lg:col-span-2">
                    <div class="flex flex-wrap items-center gap-2 px-5 pt-5 pb-3">
                        <x-icon name="inbox" class="size-5 text-slate-500 dark:text-slate-400" />
                        <h3 class="font-semibold">Départements</h3>
                        <span class="text-sm text-slate-500 dark:text-slate-400">Charge de traitement par service</span>
                    </div>

                    <ul class="divide-y divide-slate-100 px-2 pb-2 dark:divide-slate-800">
                        @forelse ($adminOverview['departments'] as $dept)
                            <li x-show="tab === 'all' || (tab === 'alert' && {{ $dept->overdue_count > 0 ? 'true' : 'false' }})"
                                @class(['rounded-lg px-3 py-3.5', 'bg-red-50 dark:bg-red-500/10' => $dept->overdue_count > 0])>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                                    <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-brand-400 to-violet-500 text-xs font-bold text-white shadow" aria-hidden="true">
                                        {{ mb_strtoupper(mb_substr($dept->name, 0, 2)) }}
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('admin.users.index', ['department' => $dept->id]) }}" class="truncate font-medium hover:text-brand-600 dark:hover:text-brand-300">{{ $dept->name }}</a>
                                        <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                            {{ $dept->active_users_count }} actif(s) / {{ $dept->users_count }} membre(s)
                                            @if ($dept->manager) · {{ $dept->manager->name }} @endif
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-3 text-sm">
                                        @if ($dept->overdue_count > 0)
                                            <span class="font-medium text-red-600 dark:text-red-400">{{ $dept->overdue_count }} en retard</span>
                                        @endif
                                        @if ($dept->open_count > 0)
                                            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs tabular-nums text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $dept->open_count }} en cours</span>
                                        @endif
                                        <span @class([
                                            'inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium dark:bg-slate-800',
                                            'text-red-700 dark:text-red-300' => $dept->overdue_count > 2,
                                            'text-amber-700 dark:text-amber-300' => $dept->overdue_count > 0 && $dept->overdue_count <= 2,
                                            'text-slate-700 dark:text-slate-200' => $dept->overdue_count === 0,
                                        ])>
                                            <span @class([
                                                'size-1.5 rounded-full',
                                                'bg-red-500' => $dept->overdue_count > 2,
                                                'bg-amber-500' => $dept->overdue_count > 0 && $dept->overdue_count <= 2,
                                                'bg-brand-500' => $dept->overdue_count === 0 && $dept->open_count > 0,
                                                'bg-emerald-500' => $dept->overdue_count === 0 && $dept->open_count === 0,
                                            ]) aria-hidden="true"></span>
                                            {{ $dept->overdue_count > 2 ? 'Urgent' : ($dept->overdue_count > 0 ? 'En retard' : ($dept->open_count > 0 ? 'En cours' : 'À jour')) }}
                                        </span>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="px-3 py-8 text-center text-slate-500">Aucun département actif.</li>
                        @endforelse
                    </ul>
                </section>

                {{-- Colonne de droite --}}
                <div class="space-y-6">
                    {{-- Point d'attention --}}
                    <section class="relative overflow-hidden rounded-xl border border-brand-300/40 bg-linear-to-br from-white via-brand-50 to-violet-100 p-5 dark:border-brand-500/30 dark:from-slate-900 dark:via-brand-900/40 dark:to-violet-900/50">
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-brand-500/10 px-2 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">✦ Point d'attention</span>
                        @if ($adminOverview['attention'])
                            <h3 class="mt-3 font-semibold">{{ $adminOverview['attention']->name }} a {{ $adminOverview['attention']->overdue_count }} traitement(s) en retard</h3>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                Sur {{ $adminOverview['attention']->open_count }} dossier(s) en cours. Ce département mérite une attention prioritaire.
                            </p>
                        @else
                            <h3 class="mt-3 font-semibold">Aucun retard dans l'organisation</h3>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Tous les traitements ouverts sont dans les délais.</p>
                        @endif
                        @if (Route::has('statistics.index'))
                            <a href="{{ route('statistics.index') }}" class="mt-4 inline-flex rounded-full bg-white px-4 py-1.5 text-sm font-medium text-slate-900 shadow-sm hover:bg-slate-100 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700">Voir les statistiques</a>
                        @endif
                    </section>

                    {{-- Suivi global --}}
                    <section class="card p-5">
                        <div class="flex items-center gap-2">
                            <x-icon name="chart" class="size-5 text-slate-500 dark:text-slate-400" />
                            <h3 class="font-semibold">Suivi global</h3>
                        </div>
                        <dl class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                            <div class="flex items-center justify-between gap-3 py-3">
                                <dt class="text-slate-600 dark:text-slate-300">Taux de réponse</dt>
                                <dd class="flex items-center gap-3">
                                    <span class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"><span class="block h-full rounded-full bg-brand-500" style="width: {{ $adminOverview['totals']['answer_rate'] ?? 0 }}%"></span></span>
                                    <span class="w-10 text-right font-semibold tabular-nums">{{ $adminOverview['totals']['answer_rate'] !== null ? $adminOverview['totals']['answer_rate'].'%' : '—' }}</span>
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 py-3">
                                <dt class="text-slate-600 dark:text-slate-300">Traitements ouverts</dt>
                                <dd class="font-semibold tabular-nums">{{ $adminOverview['totals']['open'] }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 py-3">
                                <dt class="text-slate-600 dark:text-slate-300">Délais dépassés</dt>
                                <dd class="font-semibold tabular-nums {{ $adminOverview['totals']['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : '' }}">{{ $adminOverview['totals']['overdue'] }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 py-3">
                                <dt class="text-slate-600 dark:text-slate-300">Départements actifs</dt>
                                <dd class="font-semibold tabular-nums">{{ $adminOverview['departments']->count() }}</dd>
                            </div>
                        </dl>
                    </section>

                    {{-- Actions rapides --}}
                    <section class="card p-5">
                        <h3 class="font-semibold">Actions rapides</h3>
                        <ul class="mt-3 space-y-2">
                            <li>
                                <a href="{{ route('admin.users.create') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">
                                    <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300"><x-icon name="plus" class="size-3.5" /></span>
                                    Créer un utilisateur
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('admin.departments.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">
                                    <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300"><x-icon name="inbox" class="size-3.5" /></span>
                                    Gérer les départements
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('admin.settings.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">
                                    <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300"><x-icon name="settings" class="size-3.5" /></span>
                                    Paramètres
                                </a>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>
    @endisset
</x-layouts.app>
