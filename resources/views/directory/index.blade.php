<x-layouts.app title="Départements">
    <x-page-header title="Départements" subtitle="Chaque carte indique le nombre de dossiers que vous avez envoyés au département. Cliquez sur « Personnel » pour voir ses membres." />

    @php
        $people = $departments->flatMap(fn ($department) => $department->users->map(fn ($member) => [
            'id' => $member->id,
            'name' => $member->name,
            'job_title' => $member->job_title,
            'department' => $department->name,
            'department_id' => $department->id,
        ]))->values();
    @endphp

    <div x-data="{ term: '', open: false, people: @js($people), expanded: {} }"
         x-effect="document.querySelectorAll('[data-person]').forEach(el => el.hidden = !matchesSearch(el.dataset.person, term));
                   document.querySelectorAll('[data-department]').forEach(el => el.hidden = term.trim() !== ''
                       && !matchesSearch(el.dataset.department, term) && !el.querySelector('[data-person]:not([hidden])'))">

        {{-- Recherche : département, nom ou fonction (propositions sous la barre) --}}
        <div class="relative mb-6 max-w-md" @click.outside="open = false" @keydown.escape="open = false">
            <label for="directory-search" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Rechercher</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input id="directory-search" type="search" placeholder="Département, nom ou fonction…" autocomplete="off"
                       x-model="term" @input="open = term.trim() !== ''" @focus="open = term.trim() !== ''"
                       class="block w-full rounded-lg border border-slate-300 bg-white py-2.5 pr-3 pl-9 dark:border-slate-700 dark:bg-slate-900">
            </div>

            <div x-show="open" x-cloak x-transition.opacity
                 class="absolute inset-x-0 z-30 mt-1 max-h-80 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <template x-for="person in people.filter(p => matchesSearch(p.name + ' ' + (p.job_title ?? ''), term)).slice(0, 8)" :key="person.id">
                    <button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-brand-50 dark:hover:bg-brand-900/40"
                            @click="open = false; expanded[person.department_id] = true; $nextTick(() => document.querySelector('[data-person-id=\'' + person.id + '\']')?.scrollIntoView({ behavior: 'smooth', block: 'center' }))">
                        <span x-text="person.name" class="block font-medium"></span>
                        <span x-text="[person.job_title, person.department].filter(Boolean).join(' · ')" class="block text-xs text-slate-500 dark:text-slate-400"></span>
                    </button>
                </template>
                <p x-show="!people.some(p => matchesSearch(p.name + ' ' + (p.job_title ?? ''), term))" class="px-3 py-2.5 text-sm text-slate-500">
                    Aucune personne pour « <span x-text="term"></span> ».
                </p>
            </div>
        </div>

        {{-- Une carte par département --}}
        <div class="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($departments as $department)
                @php($sent = (int) ($sentCounts[$department->id] ?? 0))

                <section class="card p-5" data-department="{{ $department->name.' '.$department->code }}" data-testid="department-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate font-semibold">{{ $department->name }}</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $department->users->count() }} membre(s) actif(s)
                                @if ($manager = $department->users->firstWhere('id', $department->manager_id))
                                    · Responsable : {{ $manager->name }}
                                @endif
                            </p>
                        </div>
                        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                            <x-icon name="building" class="size-5" />
                        </span>
                    </div>

                    <div class="mt-4 flex items-end justify-between gap-3">
                        <a href="{{ route('directory.dossiers', $department) }}" class="group block" title="Voir les dossiers envoyés à {{ $department->name }}">
                            <div class="text-2xl font-bold tabular-nums group-hover:text-brand-600" data-testid="sent-count">{{ $sent }}</div>
                            <div class="text-xs text-slate-500 group-hover:underline dark:text-slate-400">dossier(s) envoyé(s) à ce département</div>
                        </a>
                        <button type="button" class="btn-secondary px-3 py-1.5" @click="expanded[{{ $department->id }}] = !expanded[{{ $department->id }}]"
                                :aria-expanded="(!!expanded[{{ $department->id }}]).toString()">
                            <x-icon name="users" class="size-4" /> Personnel
                        </button>
                    </div>

                    <ul x-show="expanded[{{ $department->id }}] || term.trim() !== ''" x-cloak
                        class="mt-4 space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                        @forelse ($department->users as $member)
                            <li class="flex items-center gap-3" data-person="{{ $member->name.' '.$member->job_title }}" data-person-id="{{ $member->id }}">
                                <x-avatar :user="$member" />
                                <div class="leading-tight">
                                    <div class="font-medium">
                                        {{ $member->name }}
                                        @if ($department->manager_id === $member->id)
                                            <x-badge color="violet" class="ml-1">Responsable</x-badge>
                                        @endif
                                    </div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">{{ $member->job_title }}</div>
                                </div>
                            </li>
                        @empty
                            <li class="text-sm text-slate-500">Aucun membre actif.</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts.app>
