@php($historyRows = collect(app(\App\Services\HistoryExporter::class)->rowsFor($dossier, auth()->user())))

<section id="historique" class="card scroll-mt-24" aria-labelledby="history-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-section-title id="history-title" icon="clock" :count="$historyRows->count()">Historique complet</x-section-title>
        <div class="flex gap-2">
            <a href="{{ route('dossiers.history.pdf', $dossier) }}" class="btn-secondary px-3 py-1.5"><x-icon name="download" class="size-4" /> Exporter PDF</a>
            <a href="{{ route('dossiers.history.word', $dossier) }}" class="btn-secondary px-3 py-1.5"><x-icon name="download" class="size-4" /> Exporter Word</a>
        </div>
    </div>

    {{-- Fil chronologique, regroupé par jour --}}
    <div class="mt-6 space-y-6">
        @foreach ($historyRows->groupBy('date') as $date => $rows)
            <div>
                <div class="mb-3 inline-flex items-center gap-2 rounded-md bg-univ-surface px-2.5 py-1 text-xs font-bold text-univ-muted dark:bg-slate-800 dark:text-slate-300">
                    <x-icon name="calendar" class="size-3.5" /> {{ $date }}
                </div>

                <ol class="relative ml-3 border-l-2 border-univ-line dark:border-slate-700">
                    @foreach ($rows as $row)
                        <li class="relative pb-5 pl-6 last:pb-0">
                            <span class="absolute top-1 -left-[7px] size-3 rounded-full border-2 border-white bg-brand-500 dark:border-slate-900 dark:bg-brand-400" aria-hidden="true"></span>
                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                <span class="font-semibold text-univ-ink dark:text-white">{{ $row['action'] }}</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $row['time'] }} · {{ $row['user'] }}</span>
                            </div>
                            @if ($row['details'])
                                <p class="mt-1 text-xs leading-relaxed break-words text-slate-600 dark:text-slate-400">{{ $row['details'] }}</p>
                            @endif
                            @if ($row['comment'])
                                <p class="mt-2 rounded-md border-l-2 border-brand-300 bg-univ-surface/60 px-3 py-1.5 text-xs italic dark:border-brand-700 dark:bg-slate-950/30">{{ $row['comment'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </div>
</section>
