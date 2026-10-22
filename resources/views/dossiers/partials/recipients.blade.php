<section class="card overflow-x-auto p-0" aria-labelledby="recipients-title">
    <div class="px-6 pt-5">
        <x-section-title id="recipients-title" icon="users" :count="$dossier->recipients->count()">Destinataires</x-section-title>
    </div>
    <table class="data-table mt-4">
        <thead>
            <tr>
                <th>Destinataire</th>
                <th>Statut</th>
                <th>Réponse</th>
                <th>Échéance</th>
                <th class="text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dossier->recipients as $recipient)
                <tr>
                    <td data-label="Destinataire">
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$recipient->assignedUser" size="size-8" class="shrink-0 text-xs" />
                            <div class="min-w-0">
                                <div class="font-semibold whitespace-nowrap">{{ $recipient->assignedUser->name }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ collect([$recipient->assignedUser->job_title, $recipient->department->name])->filter()->implode(' · ') }}</div>
                            </div>
                        </div>
                    </td>
                    <td data-label="Statut"><x-badge :color="$recipient->status->color()">{{ $recipient->status->label() }}</x-badge></td>
                    <td data-label="Réponse" class="text-xs text-slate-500 dark:text-slate-400">
                        @if ($recipient->answered_at)
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $recipient->answered_at->format('d/m/Y') }}</span> à {{ $recipient->answered_at->format('H:i') }}
                        @else
                            En attente
                        @endif
                        @if ($recipient->archived_at)
                            <div>Archivé le {{ $recipient->archived_at->format('d/m/Y') }}</div>
                        @endif
                    </td>
                    <td data-label="Échéance"><x-deadline :due-at="$recipient->due_at" :closed="! $recipient->status->isOpen()" /></td>
                    <td class="text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            @if (Route::has('reminders.store'))
                                @can('remind', $recipient)
                                    <form method="POST" action="{{ route('reminders.store', $recipient) }}">
                                        @csrf
                                        <button type="submit" class="btn-secondary px-3 py-1.5">Relancer</button>
                                    </form>
                                @endcan
                            @endif
                            @can('reopen', $recipient)
                                <form method="POST" action="{{ route('treatments.reopen', $recipient) }}" class="flex gap-1" x-data="{ open: false }">
                                    @csrf
                                    <button type="button" class="btn-secondary px-3 py-1.5" x-show="!open" @click="open = true">Réouvrir</button>
                                    <input x-show="open" x-cloak type="text" name="comment" required placeholder="Motif" aria-label="Motif de la réouverture"
                                           class="w-40 rounded-lg border border-slate-300 bg-white px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900">
                                    <button x-show="open" x-cloak type="submit" class="btn-primary px-3 py-1.5">Confirmer</button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
