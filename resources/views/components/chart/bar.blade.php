@props(['title', 'subtitle' => null, 'data', 'colors' => null, 'horizontal' => true, 'unit' => 'dossier(s)', 'height' => null])

{{--
    Carte « graphique à barres » : $data = [libellé => valeur].
    Les chiffres restent lisibles en tableau (accessibilité, impression).
--}}
@php
    $labels = array_keys($data instanceof \Illuminate\Support\Collection ? $data->all() : $data);
    $values = array_map('intval', array_values($data instanceof \Illuminate\Support\Collection ? $data->all() : $data));
    $height ??= $horizontal ? max(140, count($labels) * 38 + 40).'px' : '16rem';
    $total = array_sum($values);
@endphp

<section {{ $attributes->class('card p-5') }} x-data="barChart(@js(['labels' => $labels, 'values' => $values, 'colors' => $colors, 'horizontal' => $horizontal, 'unit' => $unit]))">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="font-semibold">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
            @endif
        </div>
        <span class="shrink-0 text-sm text-slate-500 dark:text-slate-400">Total <strong class="tabular-nums text-slate-800 dark:text-white">{{ $total }}</strong></span>
    </div>

    <div class="relative mt-4" style="height: {{ $height }}">
        <canvas x-ref="canvas" role="img" aria-label="{{ $title }} : {{ collect($labels)->zip($values)->map(fn ($pair) => $pair[0].' '.$pair[1])->implode(', ') }}"></canvas>
    </div>

    <details class="mt-3 text-sm">
        <summary class="cursor-pointer text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white">Voir les chiffres</summary>
        <dl class="mt-2 divide-y divide-slate-100 dark:divide-slate-800">
            @foreach ($labels as $index => $label)
                <div class="flex justify-between py-1.5"><dt>{{ $label }}</dt><dd class="font-semibold tabular-nums">{{ $values[$index] }}</dd></div>
            @endforeach
        </dl>
    </details>

    {{ $slot }}
</section>
