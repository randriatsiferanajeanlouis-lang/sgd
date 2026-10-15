<x-layouts.app title="Statistiques">
    <x-page-header title="Statistiques" :subtitle="$scopeLabel" />

    @php
        // Délais dépassés : couleur « critique » réservée, toujours accompagnée de son libellé.
        $critical = ['light' => '#d03b3b', 'dark' => '#d03b3b'];
        $blue = ['light' => '#2a78d6', 'dark' => '#3987e5'];
        $treatmentColors = collect($treatments)->keys()->map(fn ($label) => $label === 'Délais dépassés' ? $critical : $blue)->all();
    @endphp

    {{-- Chiffres clés --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat-tile bg-brand-50 hover:translate-y-0 hover:shadow-none dark:bg-brand-500/10">
            <span class="text-xs font-medium text-brand-600 dark:text-brand-300">Dossiers</span>
            <span class="text-xl font-bold tabular-nums text-brand-600 dark:text-brand-300">{{ array_sum($dossierStatuses->all()) }}</span>
        </div>
        <div class="stat-tile bg-amber-50 hover:translate-y-0 hover:shadow-none dark:bg-amber-500/10">
            <span class="text-xs font-medium text-amber-600 dark:text-amber-300">Traitements ouverts</span>
            <span class="text-xl font-bold tabular-nums text-amber-600 dark:text-amber-300">{{ $treatments['Traitements ouverts'] }}</span>
        </div>
        <div class="stat-tile bg-red-50 hover:translate-y-0 hover:shadow-none dark:bg-red-500/10">
            <span class="text-xs font-medium text-red-600 dark:text-red-300">Délais dépassés</span>
            <span class="text-xl font-bold tabular-nums text-red-600 dark:text-red-300">{{ $treatments['Délais dépassés'] }}</span>
        </div>
        <div class="stat-tile bg-emerald-50 hover:translate-y-0 hover:shadow-none dark:bg-emerald-500/10">
            <span class="text-xs font-medium text-emerald-600 dark:text-emerald-300">Délai moyen de réponse</span>
            <span class="text-xl font-bold tabular-nums text-emerald-600 dark:text-emerald-300">{{ $averageResponseHours !== null ? $averageResponseHours.' h' : '—' }}</span>
        </div>
    </div>

    {{-- Évolution dans le temps --}}
    @if ($trends)
        <x-chart.trend class="mt-6" :trends="$trends" title="Évolution des dossiers"
                       :subtitle="count($trends['periods']['month']['series']) === 1 ? 'Dossiers émis ou reçus par le département' : 'Dossiers émis ou reçus par chaque département'" />
    @endif

    {{-- Répartitions --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-chart.bar title="Dossiers par statut" :data="$dossierStatuses" />
        <x-chart.bar title="Origine des dossiers" subtitle="Demandes internes ou externes" :data="$origins" />
        <x-chart.bar title="Traitements des destinataires" :data="$treatments" :colors="$treatmentColors" unit="traitement(s)" />
    </div>

    @if ($departments->isNotEmpty())
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-chart.bar title="Dossiers par département" :data="$departments->pluck('dossiers', 'name')"
                         :colors="$departments->map(fn ($row) => App\Services\DossierTrends::COLORS[$row['code']] ?? App\Services\DossierTrends::FALLBACK)->values()->all()" />
            <x-chart.bar title="Délais dépassés par département" subtitle="Traitements ouverts dont l’échéance est passée"
                         :data="$departments->pluck('overdue', 'name')" :colors="array_fill(0, $departments->count(), $critical)" unit="traitement(s)" />
        </div>

        <section class="card mt-6 overflow-x-auto p-0">
            <h2 class="px-6 pt-5 font-semibold">Par département</h2>
            <table class="data-table mt-3">
                <thead><tr><th>Département</th><th class="text-right">Dossiers</th><th class="text-right">Traitements ouverts</th><th class="text-right">Délais dépassés</th></tr></thead>
                <tbody>
                    @foreach ($departments as $row)
                        <tr>
                            <td data-label="Département" class="font-medium">{{ $row['name'] }}</td>
                            <td data-label="Dossiers" class="text-right tabular-nums">{{ $row['dossiers'] }}</td>
                            <td data-label="Traitements ouverts" class="text-right tabular-nums">{{ $row['open'] }}</td>
                            <td data-label="Délais dépassés" @class(['text-right tabular-nums', 'font-semibold text-red-600 dark:text-red-400' => $row['overdue'] > 0])>{{ $row['overdue'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
</x-layouts.app>
