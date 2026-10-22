<x-layouts.app title="Historique">
    <x-page-header title="Historique" kicker="Traçabilité"
                   subtitle="Dernières actions sur les dossiers dont vous pouvez consulter l’historique. Ouvrez un dossier pour son historique complet et ses exports PDF / Word." />

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-80">
            <label for="history-search" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Rechercher un dossier</label>
            <input id="history-search" name="search" type="search" value="{{ request('search') }}" placeholder="Référence ou objet"
                   class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900">
        </div>
        <button type="submit" class="btn-secondary py-2.5">Filtrer</button>
        @if (request()->filled('search'))
            <a href="{{ route('history.index') }}" class="btn-secondary py-2.5">Effacer</a>
        @endif
    </form>

    <div class="card overflow-x-auto p-0">
        <table class="data-table">
            <thead>
                <tr><th>Date</th><th>Heure</th><th>Dossier</th><th>Utilisateur</th><th>Action</th><th>Ancienne → nouvelle valeur</th><th>Commentaire</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td data-label="Date" class="whitespace-nowrap">{{ $row['date'] }}</td>
                        <td data-label="Heure">{{ $row['time'] }}</td>
                        <td data-label="Dossier" class="min-w-[15rem]">
                            <a href="{{ route('dossiers.show', $row['dossier']) }}#historique" class="font-mono font-bold whitespace-nowrap text-brand-500 hover:underline dark:text-brand-300" title="{{ $row['dossier']->subject }}">{{ $row['dossier']->reference }}</a>
                            <span class="block text-xs whitespace-nowrap text-univ-muted dark:text-slate-400" title="{{ $row['dossier']->subject }}">{{ $row['dossier']->shortSubject() }}</span>
                        </td>
                        <td data-label="Utilisateur" class="whitespace-nowrap">{{ $row['user'] }}</td>
                        <td data-label="Action" class="font-medium">{{ $row['action'] }}</td>
                        <td data-label="Ancienne → nouvelle" class="text-xs text-slate-600 dark:text-slate-400">{{ $row['details'] }}</td>
                        <td data-label="Commentaire" class="text-xs">{{ $row['comment'] }}</td>
                        <td class="text-right whitespace-nowrap"><x-view-button :href="route('dossiers.show', $row['dossier']).'#historique'" :reference="$row['dossier']->reference" /></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-slate-500">Aucune action enregistrée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.app>
