<section class="card" aria-labelledby="documents-title">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="documents-title" class="text-lg font-semibold">Pièces du dossier</h2>
        @can('downloadAll', $dossier)
            @if ($documentGroups->contains(fn ($versions) => $versions->first()->isActive()))
                <a href="{{ route('documents.zip', $dossier) }}" class="btn-secondary px-3 py-1.5"><x-icon name="download" class="size-4" /> Télécharger toutes les pièces</a>
            @endif
        @endcan
    </div>

    <ul class="mt-4 space-y-4 text-sm">
        @forelse ($documentGroups as $versions)
            @php($latest = $versions->first())
            <li class="rounded-xl border border-slate-200 p-3 dark:border-slate-800" x-data="{ versions: false, replace: false, cancel: false }">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        @include('dossiers.partials.document-link', ['document' => $latest])
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $latest->uploader->name }} · {{ $latest->created_at->format('d/m/Y H:i') }}
                            @if ($latest->response) · réponse de {{ $latest->response->author->name }} @endif
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <x-badge>V{{ $latest->version }}</x-badge>
                        <x-badge :color="$latest->status->color()">{{ $latest->status->label() }}</x-badge>
                    </div>
                </div>

                @if ($latest->cancellation_reason)
                    <p class="mt-2 text-xs text-red-700 dark:text-red-300">Annulé par {{ $latest->canceller?->name }} le {{ $latest->cancelled_at->format('d/m/Y H:i') }} : {{ $latest->cancellation_reason }}</p>
                @endif

                <div class="mt-2 flex flex-wrap gap-3 text-xs">
                    @can('replace', $latest)
                        <button type="button" class="text-brand-600 hover:underline dark:text-brand-300" @click="replace = !replace">Remplacer</button>
                    @endcan
                    @can('cancel', $latest)
                        <button type="button" class="text-red-600 hover:underline dark:text-red-400" @click="cancel = !cancel">Annuler</button>
                    @endcan
                    @if ($versions->count() > 1)
                        <button type="button" class="text-slate-600 hover:underline dark:text-slate-300" @click="versions = !versions">Versions ({{ $versions->count() }})</button>
                    @endif
                </div>

                @can('replace', $latest)
                    <form x-show="replace" x-cloak method="POST" action="{{ route('documents.replace', $latest) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-2">
                        @csrf
                        <input type="file" name="file" required class="text-xs" aria-label="Nouvelle version" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp">
                        <button type="submit" class="btn-primary px-3 py-1">Envoyer V{{ $latest->version + 1 }}</button>
                    </form>
                @endcan

                @can('cancel', $latest)
                    <form x-show="cancel" x-cloak method="POST" action="{{ route('documents.cancel', $latest) }}" class="mt-3 flex flex-wrap items-center gap-2">
                        @csrf
                        <input type="text" name="reason" required placeholder="Motif de l’annulation" aria-label="Motif de l’annulation"
                               class="min-w-48 flex-1 rounded-lg border border-slate-300 bg-white px-2 py-1 dark:border-slate-700 dark:bg-slate-900">
                        <button type="submit" class="btn-danger px-3 py-1">Confirmer l’annulation</button>
                    </form>
                @endcan

                @if ($versions->count() > 1)
                    <div x-show="versions" x-cloak class="mt-3 overflow-x-auto">
                        <table class="data-table text-xs">
                            <thead><tr><th>Version</th><th>Nom</th><th>Ajouté par</th><th>Date</th><th>Statut</th></tr></thead>
                            <tbody>
                                @foreach ($versions as $version)
                                    <tr>
                                        <td data-label="Version">V{{ $version->version }}</td>
                                        <td data-label="Nom">
                                            @can('downloadVersion', $version)
                                                <a href="{{ route('documents.download', $version) }}" class="text-brand-600 hover:underline dark:text-brand-300">{{ $version->original_name }}</a>
                                            @else
                                                {{ $version->original_name }}
                                            @endcan
                                        </td>
                                        <td data-label="Ajouté par">{{ $version->uploader->name }}</td>
                                        <td data-label="Date">{{ $version->created_at->format('d/m/Y H:i') }}</td>
                                        <td data-label="Statut"><x-badge :color="$version->status->color()">{{ $version->status->label() }}</x-badge></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </li>
        @empty
            <li class="text-slate-500">Aucune pièce jointe.</li>
        @endforelse
    </ul>

    @can('addDocument', $dossier)
        <form method="POST" action="{{ route('documents.store', $dossier) }}" enctype="multipart/form-data" class="mt-5 space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800">
            @csrf
            <x-form.attachments label="Ajouter des pièces" />
            <button type="submit" class="btn-secondary">Ajouter</button>
        </form>
    @endcan
</section>
