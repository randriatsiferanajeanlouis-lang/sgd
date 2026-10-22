{{-- actions : colonne « Voir » (et crayon « Modifier » si autorisé) sur chaque dossier, affichée par défaut. --}}
@props(['dossiers', 'actions' => true])

@php($viewer = auth()->user())

<div class="card overflow-x-auto p-0">
    <table class="data-table">
        <thead>
            <tr>
                <th>Référence</th>
                <th>Objet</th>
                <th>Expéditeur</th>
                <th>Destinataires</th>
                <th>Statut</th>
                <th>Échéance</th>
                @if ($actions)
                    <th class="text-right">Actions</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($dossiers as $dossier)
                @php($mine = $dossier->recipients->firstWhere('assigned_user_id', $viewer->id))
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td data-label="Référence" class="font-mono text-xs whitespace-nowrap">
                        <a href="{{ route('dossiers.show', $dossier) }}" class="font-semibold text-brand-600 hover:underline dark:text-brand-300">{{ $dossier->reference }}</a>
                        @if ($dossier->isExternal())
                            <x-badge color="violet" class="ml-1">Externe</x-badge>
                        @endif
                    </td>
                    <td data-label="Objet">
                        <a href="{{ route('dossiers.show', $dossier) }}" class="font-medium whitespace-nowrap hover:underline" title="{{ $dossier->subject }}">{{ $dossier->shortSubject() }}</a>
                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $dossier->typeLabel() }}</div>
                    </td>
                    <td data-label="Expéditeur" class="whitespace-nowrap">{{ $dossier->isExternal() ? ($dossier->externalRequester?->full_name ?? '—') : $dossier->creator->name }}</td>
                    <td data-label="Destinataires">
                        <div class="flex flex-wrap gap-1">
                            @foreach ($dossier->recipients as $recipient)
                                <x-badge :color="$recipient->status->color()" title="{{ $recipient->status->label() }}">{{ $recipient->assignedUser->name }} · {{ $recipient->status->label() }}</x-badge>
                            @endforeach
                        </div>
                    </td>
                    <td data-label="Statut">
                        @if ($mine)
                            <x-badge :color="$mine->status->color()">{{ $mine->status->label() }}</x-badge>
                            <div class="mt-0.5 text-xs text-slate-500">mon traitement</div>
                        @else
                            <x-badge :color="$dossier->status->color()">{{ $dossier->status->label() }}</x-badge>
                        @endif
                    </td>
                    <td data-label="Échéance" class="whitespace-nowrap">
                        <x-deadline :due-at="$mine?->due_at ?? $dossier->due_at" :closed="$mine ? ! $mine->status->isOpen() : in_array($dossier->status, [\App\Enums\DossierStatus::Answered, \App\Enums\DossierStatus::Archived], true)" />
                    </td>
                    @if ($actions)
                        <td class="text-right whitespace-nowrap">
                            <div class="inline-flex justify-end gap-2">
                                <x-view-button :href="route('dossiers.show', $dossier)" :reference="$dossier->reference" />
                                @can('update', $dossier)
                                    <a href="{{ route('dossiers.edit', $dossier) }}" class="btn-secondary min-h-8 px-2.5 py-1.5" title="Modifier" aria-label="Modifier {{ $dossier->reference }}"><x-icon name="pencil" class="size-4" /></a>
                                @endcan
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $actions ? 7 : 6 }}" class="py-10 text-center text-slate-500">Aucun dossier.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $dossiers->links() }}</div>
