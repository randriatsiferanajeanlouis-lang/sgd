@props(['directory', 'label' => 'Destinataire(s)'])

<div x-data="recipientPicker(@js($directory), @js(array_map('intval', (array) old('recipients', []))))" data-recipient-picker class="@container space-y-3">
    <div class="flex items-center justify-between gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
        <span>{{ $label }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></span>
        <span class="text-slate-400" title="Filtrez par département ou tapez un nom, une fonction : chaque personne choisie reçoit la demande.">
            <x-icon name="info" class="size-4" />
        </span>
    </div>

    <div class="grid gap-3 @md:grid-cols-[minmax(0,13rem)_1fr]">
        <div>
            <label for="recipient-department" class="mb-1.5 block text-xs text-slate-500">Département destinataire</label>
            <select id="recipient-department" x-model="departmentId" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                <option value="">Tous les départements</option>
                <template x-for="department in directory" :key="department.id">
                    <option :value="department.id" x-text="department.name"></option>
                </template>
            </select>
        </div>

        <div>
            <label for="recipient-search" class="mb-1.5 block text-xs text-slate-500">Rechercher</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input id="recipient-search" type="search" x-model="search" autocomplete="off" placeholder="Nom ou fonction…"
                       @keydown.enter.prevent="addFirst()"
                       class="block w-full rounded-lg border border-slate-300 bg-white py-2 pr-3 pl-9 text-sm dark:border-slate-700 dark:bg-slate-900">
            </div>
        </div>
    </div>

    <div class="max-h-48 overflow-y-auto rounded-lg border border-slate-300 dark:border-slate-700">
        <p x-show="!departmentId && !search.trim()" class="px-3 py-2.5 text-sm text-slate-500">Choisissez un département ou tapez un nom.</p>
        <p x-show="(departmentId || search.trim()) && availableUsers.length === 0" x-cloak class="px-3 py-2.5 text-sm text-slate-500">Aucun utilisateur actif ne correspond.</p>
        <template x-for="user in availableUsers" :key="user.id">
            <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                <input type="checkbox" class="size-4" :checked="isSelected(user)" @change="toggle(user)">
                <span>
                    <span x-text="user.name" class="font-medium"></span>
                    <span x-text="user.job_title ? '— ' + user.job_title : ''" class="text-slate-500"></span>
                    <span x-show="!departmentId" x-text="'· ' + user.department" class="text-xs text-slate-400"></span>
                </span>
            </label>
        </template>
    </div>

    <div class="relative flex flex-wrap gap-2" aria-live="polite">
        {{-- Contrôle invisible : bloque l'étape (ou l'envoi) tant qu'aucun destinataire n'est choisi. --}}
        <input type="text" tabindex="-1" aria-hidden="true" class="pointer-events-none absolute top-0 left-0 h-px w-px opacity-0"
               x-effect="$el.setCustomValidity(selected.length ? '' : 'Choisissez au moins un destinataire.')">
        <template x-for="user in selected" :key="user.id">
            <span class="inline-flex items-center gap-1.5 rounded-md border border-amber-300 bg-amber-100 px-2.5 py-1 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-100">
                <button type="button" @click="remove(user)" class="font-semibold leading-none hover:text-red-600" :aria-label="'Retirer ' + user.name">&times;</button>
                <span x-text="user.name + ' (' + user.department + ')'"></span>
                <input type="hidden" name="recipients[]" :value="user.id" :data-name="user.name + ' (' + user.department + ')'">
            </span>
        </template>
        <span x-show="selected.length === 0" class="text-sm text-slate-500">Aucun destinataire sélectionné.</span>
    </div>

    @error('recipients')
        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
    @error('recipients.*')
        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
