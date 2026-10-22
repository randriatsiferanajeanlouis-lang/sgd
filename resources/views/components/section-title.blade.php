@props(['icon', 'count' => null])

{{-- Titre de section d'une fiche : pastille d'icône, intitulé, compteur facultatif. --}}
<h2 {{ $attributes->class('flex items-center gap-2.5 text-base font-bold text-univ-ink dark:text-white') }}>
    <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-md bg-brand-50 text-brand-500 dark:bg-slate-800 dark:text-brand-300">
        <x-icon :name="$icon" class="size-4" />
    </span>
    <span>{{ $slot }}</span>
    @if (! is_null($count))
        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $count }}</span>
    @endif
</h2>
