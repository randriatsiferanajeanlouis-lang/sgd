<x-layouts.app title="Validations">
    <x-page-header title="Validations" subtitle="Dossiers transmis par vos employés pour validation." />

    <section class="card overflow-x-auto p-0">
        <h2 class="px-6 pt-5 text-lg font-semibold">À valider</h2>
        <table class="data-table mt-3">
            <thead><tr><th>Dossier</th><th>Employé</th><th>Commentaire</th><th>Urgence</th><th>Échéance</th><th></th></tr></thead>
            <tbody>
                @forelse ($pending as $validation)
                    <tr>
                        <td data-label="Dossier">
                            <div class="font-mono text-xs font-semibold">{{ $validation->dossier->reference }}</div>
                            <div>{{ $validation->dossier->subject }}</div>
                        </td>
                        <td data-label="Employé">{{ $validation->requester->name }}</td>
                        <td data-label="Commentaire" class="max-w-xs text-sm">{{ Str::limit($validation->request_comment, 120) ?: '—' }}</td>
                        <td data-label="Urgence">{{ $validation->dossier->urgencyLevel?->name ?? '—' }}</td>
                        <td data-label="Échéance"><x-deadline :due-at="$validation->dossier->due_at" /></td>
                        <td class="text-right"><a href="{{ route('dossiers.show', $validation->dossier) }}" class="btn-primary px-3 py-1.5">Examiner</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">Aucune demande de validation en attente.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="card mt-6 overflow-x-auto p-0">
        <h2 class="px-6 pt-5 text-lg font-semibold">Décisions prises</h2>
        <table class="data-table mt-3">
            <thead><tr><th>Dossier</th><th>Employé</th><th>Décision</th><th>Date</th></tr></thead>
            <tbody>
                @forelse ($decided as $validation)
                    <tr>
                        <td data-label="Dossier"><a href="{{ route('dossiers.show', $validation->dossier) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $validation->dossier->reference }}</a></td>
                        <td data-label="Employé">{{ $validation->requester->name }}</td>
                        <td data-label="Décision"><x-badge :color="$validation->status->color()">{{ $validation->status->label() }}</x-badge></td>
                        <td data-label="Date">{{ $validation->decided_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-500">Aucune décision.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="mt-4">{{ $decided->links() }}</div>
</x-layouts.app>
