@props(['number', 'title', 'description' => null, 'fields' => []])

{{-- Colonne numérotée d'un formulaire sur une page (voir x-form.page). Son état « complète » est suivi en direct. --}}
@php($hasError = collect($fields)->contains(fn ($field) => $errors->has($field)))

<section data-step="{{ $number }}" aria-labelledby="panel-{{ $number }}-title"
         {{ $attributes->class(['card scroll-mt-24 overflow-hidden p-0', 'ring-2 ring-red-300 dark:ring-red-800' => $hasError]) }}>
    <header class="flex items-start gap-4 border-b border-univ-line bg-univ-surface/60 px-5 py-4 sm:px-6 dark:border-slate-800 dark:bg-slate-950/30">
        <span class="grid size-10 shrink-0 place-items-center rounded-xl text-sm font-extrabold transition"
              :class="done[{{ $number }}] ? 'bg-emerald-500 text-white' : 'bg-brand-500 text-white dark:bg-brand-400 dark:text-slate-950'">
            <span x-show="!done[{{ $number }}]">{{ $number }}</span>
            <svg x-show="done[{{ $number }}]" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-bold tracking-[0.08em] text-univ-muted uppercase dark:text-slate-400">Étape {{ $number }}</p>
            <h2 id="panel-{{ $number }}-title" class="text-base font-bold text-univ-ink dark:text-white">{{ $title }}</h2>
            @if ($description)
                <p class="mt-0.5 text-xs text-univ-muted dark:text-slate-400">{{ $description }}</p>
            @endif
        </div>
        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold"
              :class="done[{{ $number }}] ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/10 text-amber-700 dark:text-amber-300'"
              x-text="done[{{ $number }}] ? 'Complète' : 'À compléter'">À compléter</span>
    </header>

    <div class="space-y-5 p-5 sm:p-6">
        {{ $slot }}
    </div>
</section>
