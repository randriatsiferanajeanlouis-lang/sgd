<x-layouts.app title="Nouvelle demande externe">
    <x-form.page title="Nouvelle demande externe" kicker="Demandes externes"
                 subtitle="Enregistrez la demande reçue d’une personne ou d’un organisme extérieur, puis transmettez-la au(x) responsable(s) concerné(s)."
                 :action="route('external-requests.store')" :cancel="route('external-requests.index')" :back="route('external-requests.index')" back-label="Demandes externes"
                 submit="Enregistrer et transmettre" submit-icon="send" :panels="2" confirm
                 confirm-question="Êtes-vous sûr de vouloir transmettre cette demande externe à"
                 :complete-types="$completeTypes"
                 enctype="multipart/form-data"
                 :x-data="'externalDueDate('.Js::from($urgencyLevels).', '.Js::from(old('received_at', now()->toDateString())).', '.Js::from(old('urgency_level_id')).')'">

        {{-- Étape 1 : objet, type, canal et demandeur --}}
        <x-form.panel :number="1" title="Demande et demandeur" description="Ce qui est demandé, par quel canal et par qui."
                      :fields="['subject', 'request_type_id', 'request_type_other', 'reception_channel', 'requester_name', 'requester_organization', 'requester_phone', 'requester_email']">
            <x-form.field name="subject" label="Objet" required maxlength="255" placeholder="Ex. : Demande de stage, réclamation, partenariat…" />

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="request_type_id" label="Type de demande" :options="$requestTypes" placeholder="Sélectionner" required x-model="type" />
                <x-form.select name="reception_channel" label="Canal de réception" :options="$channels" placeholder="Sélectionner" required />
            </div>

            <x-form.other-request-type />

            <x-form.complete-notice />

            <fieldset class="rounded-xl border border-univ-line bg-univ-surface/60 p-4 dark:border-slate-800 dark:bg-slate-950/30">
                <legend class="flex items-center gap-2 px-1 text-sm font-semibold text-univ-ink dark:text-white">
                    <x-icon name="user" class="size-4 text-brand-500 dark:text-brand-300" /> Demandeur
                </legend>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.field name="requester_name" label="Nom du demandeur" required />
                    <x-form.field name="requester_organization" label="Organisme" required-when="complete" />
                    <x-form.field name="requester_phone" label="Téléphone" type="tel" required-when="complete" />
                    <x-form.field name="requester_email" label="E-mail" type="email" required-when="complete" />
                </div>
            </fieldset>
        </x-form.panel>

        {{-- Étape 2 : réception / échéance, transmission, description --}}
        <x-form.panel :number="2" title="Réception, transmission et description" description="Date de réception, urgence, responsables destinataires et détail de la demande."
                      :fields="['received_at', 'urgency_level_id', 'recipients', 'recipients.*', 'description', 'attachments', 'attachments.*']">
            <fieldset class="rounded-xl border border-univ-line bg-univ-surface/60 p-4 dark:border-slate-800 dark:bg-slate-950/30">
                <legend class="flex items-center gap-2 px-1 text-sm font-semibold text-univ-ink dark:text-white">
                    <x-icon name="clock" class="size-4 text-brand-500 dark:text-brand-300" /> Réception et échéance
                </legend>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="received_at" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Date de réception <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
                        <input id="received_at" type="date" name="received_at" x-model="receivedAt" max="{{ now()->toDateString() }}" required
                               class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        @error('received_at')
                            <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <x-form.select name="urgency_level_id" label="Urgence" required placeholder="Sélectionner"
                                   :options="$urgencyLevels->mapWithKeys(fn ($level) => [$level->id => $level->name.' — '.($level->response_delay_hours % 24 === 0 ? ($level->response_delay_hours / 24).' jour(s)' : $level->response_delay_hours.' h')])"
                                   x-model="urgencyId" />
                </div>
                <div class="mt-4 flex items-start gap-3 rounded-lg border border-dashed border-brand-300 bg-white px-3 py-2.5 dark:border-brand-700 dark:bg-slate-900">
                    <x-icon name="calendar" class="mt-0.5 size-4 shrink-0 text-brand-500 dark:text-brand-300" />
                    <div class="text-sm">
                        <div class="text-xs font-semibold text-univ-muted dark:text-slate-400">Date d’échéance (calculée)</div>
                        <div class="font-medium" x-text="preview" aria-live="polite"></div>
                    </div>
                </div>
            </fieldset>

            <div class="space-y-2">
                <x-recipient-picker :directory="$directory" label="Département → Responsable / Utilisateur (plusieurs possibles)" />
                <p class="flex items-center gap-1.5 text-xs text-univ-muted dark:text-slate-400">
                    <x-icon name="bell" class="size-3.5" /> Chaque responsable choisi est prévenu par notification dès l’enregistrement.
                </p>
            </div>

            <x-form.textarea name="description" label="Détail de la demande" rows="5" required-when="complete"
                             placeholder="Résumé du courrier ou de l’échange, éléments attendus…" />

            <x-form.section title="Pièces jointes (facultatif)" icon="paperclip" :open="false" :fields="['attachments', 'attachments.*']">
                <x-form.attachments label="Pièces jointes (courrier, CV…)" />
            </x-form.section>
        </x-form.panel>
    </x-form.page>
</x-layouts.app>
