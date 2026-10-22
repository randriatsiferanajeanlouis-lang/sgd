@if ($myRecipient)
    <section class="card border-t-4 border-t-brand-500 dark:border-t-brand-400" aria-labelledby="my-treatment-title">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <x-section-title id="my-treatment-title" icon="inbox">Mon traitement</x-section-title>
            <div class="flex items-center gap-2">
                <x-badge :color="$myRecipient->status->color()">{{ $myRecipient->status->label() }}</x-badge>
                <x-deadline :due-at="$myRecipient->due_at" :closed="! $myRecipient->status->isOpen()" />
            </div>
        </div>

        @if ($myRecipient->hasPendingValidation())
            <p class="mt-4 rounded-lg bg-amber-50 px-4 py-2 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                Dossier transmis à votre responsable : vous pourrez répondre après sa décision.
            </p>
        @endif

        @can('requestValidation', $myRecipient)
            <details class="mt-4 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <summary class="cursor-pointer font-medium">Transmettre au responsable</summary>
                @php($validatorName = \App\Services\ValidationService::validatorFor(auth()->user())?->name)
                <form method="POST" action="{{ route('validations.store', $myRecipient) }}" class="mt-3 space-y-3" novalidate
                      x-data="confirmSend({ fixed: @js($validatorName ? $validatorName.' (votre responsable)' : null) })" @submit="validate($event)">
                    @csrf
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Votre responsable ({{ $validatorName }}) validera ou refusera avant votre réponse.
                    </p>
                    <x-form.textarea name="comment" label="Commentaire pour le responsable (facultatif)" rows="3" />
                    <button type="submit" class="btn-secondary" :disabled="submitting">Transmettre au responsable</button>
                    <x-send-confirmation title="Confirmer la transmission" question="Êtes-vous sûr de vouloir transmettre ce dossier pour validation à" confirm-label="Confirmer la transmission" />
                </form>
            </details>
        @endcan

        @can('forward', $myRecipient)
            <details class="mt-4 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <summary class="cursor-pointer font-medium">Transmettre à un autre utilisateur</summary>
                <form method="POST" action="{{ route('treatments.forward', $myRecipient) }}" class="mt-3 space-y-3" novalidate
                      x-data="confirmSend({ select: '#user_id' })" @submit="validate($event)">
                    @csrf
                    <x-form.select name="user_id" label="Destinataire" required placeholder="Sélectionner"
                                   :options="$forwardTargets" />
                    <x-form.field name="comment" label="Commentaire (facultatif)" />
                    <p x-show="recipientError" x-cloak class="flex items-center gap-1.5 text-sm font-semibold text-red-600 dark:text-red-400" role="alert">
                        <x-icon name="info" class="size-4" /> Aucun destinataire sélectionné : choisissez la personne à qui transmettre le dossier.
                    </p>
                    <button type="submit" class="btn-secondary" :disabled="submitting">Transmettre</button>
                    <x-send-confirmation title="Confirmer la transmission" question="Êtes-vous sûr de vouloir transmettre ce dossier à" confirm-label="Confirmer la transmission" />
                </form>
            </details>
        @endcan

        @can('respond', $myRecipient)
            <form method="POST" action="{{ route('treatments.respond', $myRecipient) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf

                @if ($dossier->isExternal())
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium">Décision</legend>
                        <div class="flex gap-4">
                            @foreach (\App\Enums\ResponseDecision::cases() as $decision)
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="decision" value="{{ $decision->value }}" @checked(old('decision') === $decision->value) required>
                                    {{ $decision->label() }}
                                </label>
                            @endforeach
                        </div>
                        @error('decision')
                            <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </fieldset>
                @endif

                <x-form.textarea name="content" :label="$dossier->isExternal() ? 'Commentaire' : 'Réponse'" rows="5" required />

                @if ($dossier->isExternal())
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.textarea name="justification" label="Justification (facultatif)" rows="3" />
                        <x-form.textarea name="recommendation" label="Recommandation (facultatif)" rows="3" />
                    </div>
                @endif

                <x-form.attachments label="Pièce(s) jointe(s) (facultatif)" />

                <button type="submit" class="btn-primary">{{ $dossier->isExternal() ? 'Envoyer la décision au RH' : 'Envoyer la réponse' }}</button>
            </form>
        @endcan

        @can('archive', $myRecipient)
            <form method="POST" action="{{ route('treatments.archive', $myRecipient) }}" class="mt-4">
                @csrf
                <button type="submit" class="btn-secondary">Archiver mon traitement</button>
            </form>
        @endcan
    </section>
@endif
