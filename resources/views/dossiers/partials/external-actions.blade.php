@if ($dossier->isExternal() && auth()->user()->hasPermission('manage_external_requests'))
    @php($decisions = $dossier->responses->whereNotNull('decision'))

    <section class="card border-violet-300 dark:border-violet-800" aria-labelledby="hr-title">
        <h2 id="hr-title" class="text-lg font-semibold">Suivi RH</h2>

        <div class="mt-3 flex flex-wrap gap-2 text-sm">
            <x-badge color="green">{{ $decisions->where('decision', \App\Enums\ResponseDecision::Positive)->count() }} décision(s) positive(s)</x-badge>
            <x-badge color="red">{{ $decisions->where('decision', \App\Enums\ResponseDecision::Negative)->count() }} décision(s) négative(s)</x-badge>
            <x-badge>{{ $dossier->recipients->filter(fn ($recipient) => $recipient->status->isOpen())->count() }} traitement(s) en attente de réponse</x-badge>
        </div>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Toutes les décisions sont conservées, même divergentes : aucune majorité n’est calculée.</p>

        @can('recordOfficialResponse', $dossier)
            <form method="POST" action="{{ route('external-requests.respond', $dossier) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <h3 class="font-semibold">Enregistrer la réponse officielle</h3>
                <x-form.textarea name="content" label="Réponse envoyée au demandeur" rows="5" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.select name="channel" label="Canal de réponse" required placeholder="Sélectionner"
                                   :options="collect(\App\Enums\ReceptionChannel::cases())->mapWithKeys(fn ($channel) => [$channel->value => $channel->label()])" />
                    <x-form.field name="sent_at" label="Date d’envoi" type="date" :value="now()->toDateString()" required max="{{ now()->toDateString() }}" />
                </div>
                <x-form.field name="document" label="Document (facultatif)" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp" />
                <button type="submit" class="btn-primary">Enregistrer la réponse officielle</button>
            </form>
        @elseif (in_array($dossier->status, [\App\Enums\DossierStatus::Sent, \App\Enums\DossierStatus::InProgress], true))
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">La réponse officielle pourra être enregistrée dès qu’un responsable aura rendu sa décision.</p>
        @endif

        @can('archiveExternal', $dossier)
            <form method="POST" action="{{ route('external-requests.archive', $dossier) }}" class="mt-5" onsubmit="return confirm('Archiver définitivement ce dossier ?')">
                @csrf
                <button type="submit" class="btn-secondary">Archiver le dossier</button>
            </form>
        @endcan
    </section>
@endif
