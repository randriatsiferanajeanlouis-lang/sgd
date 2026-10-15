<x-layouts.app title="Nouvelle demande externe">
    <x-form.modal title="Nouvelle demande externe" subtitle="Enregistrement par le RH et transmission au(x) responsable(s) concerné(s)."
                  :action="route('external-requests.store')" :cancel="route('external-requests.index')" submit="Enregistrer et transmettre" submit-icon="send"
                  :steps="[
                      ['title' => 'Demande et demandeur', 'fields' => ['subject', 'request_type_id', 'reception_channel', 'requester_name', 'requester_organization', 'requester_phone', 'requester_email']],
                      ['title' => 'Réception, transmission et description', 'fields' => ['received_at', 'urgency_level_id', 'recipients', 'recipients.*', 'description', 'attachments', 'attachments.*']],
                  ]"
                  :complete-types="$completeTypes"
                  enctype="multipart/form-data"
                  :x-data="'externalDueDate('.Js::from($urgencyLevels).', '.Js::from(old('received_at', now()->toDateString())).', '.Js::from(old('urgency_level_id')).')'">

        {{-- Étape 1 : objet, type, canal et demandeur --}}
        <x-form.step :number="1">
            <x-form.field name="subject" label="Objet" required maxlength="255" />

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="request_type_id" label="Type" :options="$requestTypes" placeholder="Sélectionner" required x-model="type" />
                <x-form.select name="reception_channel" label="Canal" :options="$channels" placeholder="Sélectionner" required />
            </div>

            <x-form.complete-notice />

            <x-form.section title="Demandeur" icon="user" :fields="['requester_name', 'requester_organization', 'requester_phone', 'requester_email']">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.field name="requester_name" label="Nom du demandeur" required />
                    <x-form.field name="requester_organization" label="Organisme" required-when="complete" />
                    <x-form.field name="requester_phone" label="Téléphone" type="tel" required-when="complete" />
                    <x-form.field name="requester_email" label="E-mail" type="email" required-when="complete" />
                </div>
            </x-form.section>
        </x-form.step>

        {{-- Étape 2 : réception / échéance, transmission, description --}}
        <x-form.step :number="2">
            <x-form.section title="Réception et échéance" icon="clock" :fields="['received_at', 'urgency_level_id']">
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
                <div>
                    <div class="mb-1.5 text-sm font-medium text-slate-700 dark:text-slate-300">Date d’échéance (calculée)</div>
                    <div class="rounded-lg border border-dashed border-slate-300 px-3 py-2.5 text-sm dark:border-slate-700" x-text="preview" aria-live="polite"></div>
                </div>
            </x-form.section>

            <x-form.section title="Transmission" icon="users" :fields="['recipients', 'recipients.*']">
                <x-recipient-picker :directory="$directory" label="Département → Responsable / Utilisateur (plusieurs possibles)" />
            </x-form.section>

            <x-form.section title="Description" icon="document" :fields="['description']">
                <x-form.textarea name="description" label="Détail de la demande" rows="5" required-when="complete" />
            </x-form.section>

            <x-form.section title="Pièces jointes" icon="paperclip" :open="false" :fields="['attachments', 'attachments.*']">
                <x-form.attachments label="Pièces jointes (courrier, CV…)" />
            </x-form.section>
        </x-form.step>
    </x-form.modal>
</x-layouts.app>
