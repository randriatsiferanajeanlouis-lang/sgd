@props(['trends', 'title', 'subtitle', 'testid' => null])

{{--
    Carte « courbes » (Semaine / Mois / Année) alimentée par App\Services\DossierTrends.
    Une seule série : son nom figure dans le titre, pas de légende. Plusieurs : légende avec totaux.
--}}
@php($single = count($trends['periods']['month']['series']) === 1)

<section {{ $attributes->class('card') }} x-data="trendChart(@js($trends))" @if ($testid) data-testid="{{ $testid }}" @endif>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="font-semibold">{{ $title }}{{ $single ? ' — '.$trends['periods']['month']['series'][0]['name'] : '' }}</h2>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                {{ $subtitle }},
                <span x-text="{ week: '7 derniers jours', month: '30 derniers jours', year: '12 derniers mois' }[period]"></span>
            </p>
        </div>

        <div class="inline-flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-700" role="group" aria-label="Période">
            @foreach ($trends['periods'] as $key => $period)
                <button type="button" @click="period = '{{ $key }}'" :aria-pressed="(period === '{{ $key }}').toString()"
                        :class="period === '{{ $key }}' ? 'bg-brand-500 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white'"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition">{{ $period['label'] }}</button>
            @endforeach
        </div>
    </div>

    @unless ($single)
        <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm" aria-label="Légende">
            <template x-for="serie in current.series" :key="serie.name">
                <li class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                    <span class="inline-block h-0.5 w-4 rounded-full" :style="{ backgroundColor: colorOf(serie) }" aria-hidden="true"></span>
                    <span x-text="serie.name"></span>
                    <span class="font-semibold tabular-nums text-slate-800 dark:text-white" x-text="serie.total"></span>
                </li>
            </template>
        </ul>
    @endunless

    <div class="relative mt-4 h-64 sm:h-72">
        <canvas x-ref="canvas" role="img" :aria-label="@js($title) + ', ' + current.label.toLowerCase()"></canvas>
    </div>

    <details class="mt-4 text-sm">
        <summary class="cursor-pointer text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white">Voir les chiffres en tableau</summary>
        <div class="mt-3 overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Période</th>
                        <template x-for="serie in current.series" :key="serie.name"><th class="text-right" x-text="serie.name"></th></template>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(label, index) in current.labels" :key="label">
                        <tr>
                            <td x-text="label"></td>
                            <template x-for="serie in current.series" :key="serie.name"><td class="text-right tabular-nums" x-text="serie.data[index]"></td></template>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </details>
</section>
