<section id="historique" class="card scroll-mt-24 overflow-x-auto p-0" aria-labelledby="history-title">
    <div class="flex flex-wrap items-center justify-between gap-2 px-6 pt-5">
        <h2 id="history-title" class="text-lg font-semibold">Historique complet</h2>
        <div class="flex gap-2">
            <a href="{{ route('dossiers.history.pdf', $dossier) }}" class="btn-secondary px-3 py-1.5"><x-icon name="download" class="size-4" /> Exporter PDF</a>
            <a href="{{ route('dossiers.history.word', $dossier) }}" class="btn-secondary px-3 py-1.5"><x-icon name="download" class="size-4" /> Exporter Word</a>
        </div>
    </div>

    <table class="data-table mt-3">
        <thead>
            <tr><th>Date</th><th>Heure</th><th>Utilisateur</th><th>Action</th><th>Ancienne → nouvelle valeur</th><th>Commentaire</th></tr>
        </thead>
        <tbody>
            @foreach (app(\App\Services\HistoryExporter::class)->rowsFor($dossier, auth()->user()) as $row)
                <tr>
                    <td data-label="Date" class="whitespace-nowrap">{{ $row['date'] }}</td>
                    <td data-label="Heure">{{ $row['time'] }}</td>
                    <td data-label="Utilisateur" class="whitespace-nowrap">{{ $row['user'] }}</td>
                    <td data-label="Action" class="font-medium">{{ $row['action'] }}</td>
                    <td data-label="Ancienne → nouvelle" class="text-xs text-slate-600 dark:text-slate-400">{{ $row['details'] }}</td>
                    <td data-label="Commentaire" class="text-xs">{{ $row['comment'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
