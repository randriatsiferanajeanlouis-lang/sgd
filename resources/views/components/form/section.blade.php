@props(['title', 'icon' => null, 'open' => true, 'fields' => []])

{{-- Section repliable (+ / −) ; elle s'ouvre d'office si l'un de ses champs est en erreur. --}}
@php
    $open = $open || collect($fields)->contains(fn ($field) => $errors->has($field));
@endphp

<section x-data="{ open: @js($open) }" {{ $attributes->class('rounded-lg border border-slate-200 dark:border-slate-700') }}>
    <h2>
        <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-expanded="{{ $open ? 'true' : 'false' }}"
                class="flex w-full items-center justify-between gap-3 rounded-lg px-4 py-3 text-left text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800/60">
            <span class="flex items-center gap-2.5">
                @if ($icon)
                    <x-icon :name="$icon" class="size-4 text-slate-500 dark:text-slate-400" />
                @endif
                {{ $title }}
            </span>
            <x-icon name="minus" class="size-4" x-show="open" />
            <x-icon name="plus" class="size-4" x-show="!open" x-cloak />
        </button>
    </h2>

    <div x-show="open" @unless ($open) x-cloak @endunless class="space-y-4 border-t border-slate-200 px-4 py-4 dark:border-slate-700">
        {{ $slot }}
    </div>
</section>
