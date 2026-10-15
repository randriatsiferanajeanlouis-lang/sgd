@props(['name' => 'search', 'label' => 'Rechercher', 'value' => null, 'placeholder' => null])

{{--
    Barre de recherche avec propositions instantanées : à chaque frappe, la page
    de liste renvoie ses premiers résultats (mêmes filtres, mêmes droits) qui
    s'affichent sous la zone. Entrée sans proposition choisie : filtrage classique.
--}}
<div x-data="liveSearch(@js(old($name, $value) ?? ''))" @click.outside="close()" @keydown.escape="close()" class="relative">
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $label }}</label>

    <div class="relative">
        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
        <input id="{{ $name }}" name="{{ $name }}" type="search" autocomplete="off" placeholder="{{ $placeholder }}"
               x-model="term" @input="lookup()" @focus="lookup()"
               @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter="choose($event)"
               role="combobox" aria-autocomplete="list" aria-controls="{{ $name }}-suggestions" :aria-expanded="open.toString()"
               {{ $attributes->class('block w-full rounded-lg border border-slate-300 bg-white py-2.5 pr-3 pl-9 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-900') }}>
    </div>

    <div x-show="open" x-cloak x-transition.opacity
         class="absolute inset-x-0 z-30 mt-1 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg sm:min-w-96 dark:border-slate-700 dark:bg-slate-900">
        <p x-show="loading && results.length === 0" class="px-3 py-2.5 text-sm text-slate-500">Recherche…</p>
        <p x-show="!loading && results.length === 0" class="px-3 py-2.5 text-sm text-slate-500">Aucun résultat pour « <span x-text="term"></span> ».</p>

        <ul id="{{ $name }}-suggestions" role="listbox" class="max-h-80 overflow-y-auto">
            <template x-for="(result, index) in results" :key="result.url">
                <li role="option" :aria-selected="(index === active).toString()">
                    <a :href="result.url" @mouseenter="active = index"
                       :class="index === active ? 'bg-brand-50 dark:bg-brand-900/40' : ''"
                       class="block px-3 py-2 text-sm">
                        <span class="block font-medium text-slate-800 dark:text-slate-100">
                            <template x-for="(part, i) in highlight(result.label)" :key="i">
                                <span x-text="part.text" :class="part.match ? 'rounded bg-amber-200 text-slate-900 dark:bg-amber-500/40 dark:text-white' : ''"></span>
                            </template>
                        </span>
                        <span x-show="result.detail" x-text="result.detail" class="block text-xs text-slate-500 dark:text-slate-400"></span>
                    </a>
                </li>
            </template>
        </ul>

        <p x-show="results.length > 0" class="border-t border-slate-100 px-3 py-1.5 text-xs text-slate-400 dark:border-slate-800">
            Entrée pour filtrer toute la liste · ↑ ↓ pour choisir
        </p>
    </div>
</div>
