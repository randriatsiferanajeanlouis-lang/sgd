<x-layouts.app title="Mes dossiers">
    <x-page-header title="Mes dossiers">
        <x-slot:actions>
            @can('createInternal', App\Models\Dossier::class)
                <a href="{{ route('dossiers.create') }}" class="btn-primary"><x-icon name="plus" /> Nouvelle demande</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Synthèse : total général et total par département (un clic filtre la liste) --}}
    <section class="mb-6" aria-label="Totaux des dossiers">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
            <a href="{{ route('dossiers.index', ['box' => 'all']) }}" data-testid="total-all"
               @class([
                   'col-span-2 flex flex-col justify-between gap-3 rounded-lg bg-univ-deep p-4 text-white transition hover:-translate-y-0.5 hover:shadow-[0_12px_26px_rgba(26,26,140,0.25)] sm:col-span-1',
                   'ring-2 ring-univ-electric ring-offset-2 dark:ring-offset-slate-950' => $box === 'all' && ! $selectedDepartment,
               ])>
                <span class="text-[11px] font-extrabold tracking-[0.1em] text-[#c9c9ff] uppercase">Tous mes dossiers</span>
                <span class="text-3xl leading-none font-extrabold tabular-nums">{{ $grandTotal }}</span>
                <span class="text-xs text-[#dedffd]">Total général</span>
            </a>

            @foreach ($departmentTotals as $row)
                @php($active = (int) $selectedDepartment === $row['department']->id)
                <a href="{{ route('dossiers.index', ['box' => 'all', 'department' => $row['department']->id]) }}" data-testid="department-total"
                   title="Voir mes dossiers concernant {{ $row['department']->name }}"
                   @class([
                       'group flex flex-col justify-between gap-3 rounded-lg border border-t-2 border-univ-line border-t-brand-500 bg-white p-4 transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-[0_12px_26px_rgba(26,26,140,0.12)] dark:border-slate-800 dark:border-t-brand-300 dark:bg-slate-900',
                       'bg-brand-50 ring-2 ring-univ-electric ring-offset-2 dark:bg-slate-800 dark:ring-offset-slate-950' => $active,
                   ])>
                    <span class="truncate text-[11px] font-extrabold tracking-[0.08em] text-brand-500 uppercase dark:text-brand-300">{{ $row['department']->name }}</span>
                    <span class="text-3xl leading-none font-extrabold text-univ-ink tabular-nums group-hover:text-brand-500 dark:text-white">{{ $row['total'] }}</span>
                    <span class="text-xs text-univ-muted dark:text-slate-400">dossier(s)</span>
                </a>
            @endforeach
        </div>

        @if ($selectedDepartment)
            @php($current = $departmentTotals->firstWhere('department.id', (int) $selectedDepartment))
            <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-univ-muted dark:text-slate-400">
                Liste filtrée : dossiers concernant <strong class="text-brand-500 dark:text-brand-300">{{ $current['department']->name ?? '—' }}</strong>
                <a href="{{ route('dossiers.index', ['box' => $box]) }}" class="font-bold text-brand-500 hover:underline dark:text-brand-300">Retirer le filtre</a>
            </p>
        @endif
    </section>

    <nav class="mb-6 flex flex-wrap gap-2" aria-label="Boîtes">
        @foreach (['received' => 'Reçus', 'sent' => 'Envoyés', 'archived' => 'Archivés', 'all' => 'Tous'] as $key => $label)
            <a href="{{ route('dossiers.index', array_filter(['box' => $key, 'department' => $selectedDepartment])) }}" class="{{ $box === $key ? 'btn-primary' : 'btn-secondary' }}" @if ($box === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @include('dossiers.partials.filters')

    <x-dossier-table :dossiers="$dossiers" />
</x-layouts.app>
