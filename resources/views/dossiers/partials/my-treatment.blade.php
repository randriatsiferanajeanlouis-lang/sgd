@if ($myRecipient)
    <section class="card border-brand-300 dark:border-brand-700" aria-labelledby="my-treatment-title">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="my-treatment-title" class="text-lg font-semibold">Mon traitement</h2>
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
                <form method="POST" action="{{ route('validations.store', $myRecipient) }}" class="mt-3 space-y-3">
                    @csrf
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Votre responsable ({{ \App\Services\ValidationService::validatorFor(auth()->user())?->name }}) validera ou refusera avant votre réponse.
                    </p>
                    <x-form.textarea name="comment" label="Commentaire pour le responsable (facultatif)" rows="3" />
                    <button type="submit" class="btn-secondary">Transmettre au responsable</button>
                </form>
            </details>
        @endcan

        @can('forward', $myRecipient)
            <details class="mt-4 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <summary class="cursor-pointer font-medium">Transmettre à un autre utilisateur</summary>
                <form method="POST" action="{{ route('treatments.forward', $myRecipient) }}" class="mt-3 space-y-3">
                    @csrf
                    <x-form.select name="user_id" label="Destinataire" required placeholder="Sélectionner"
                                   :options="$forwardTargets" />
                    <x-form.field name="comment" label="Commentaire (facultatif)" />
                    <button type="submit" class="btn-secondary">Transmettre</button>
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
