<x-layouts.app title="Nouvelle demande">
    <x-form.modal title="Nouvelle demande interne" subtitle="La demande est envoyée à des personnes précises, choisies par département."
                  :action="route('dossiers.store')" :cancel="route('dossiers.index')" submit="Envoyer la demande" submit-icon="send"
                  :steps="[
                      ['title' => 'Demande et échéance', 'fields' => ['subject', 'request_type_id', 'urgency_level_id', 'due_at']],
                      ['title' => 'Destinataires et description', 'fields' => ['recipients', 'recipients.*', 'description', 'attachments', 'attachments.*']],
                  ]"
                  :complete-types="$completeTypes"
                  enctype="multipart/form-data" :x-data="'dueDateHelper('.Js::from($urgencyLevels).', '.Js::from(old('due_at', '')).')'">

        {{-- Étape 1 : objet, type, urgence et échéance --}}
        <x-form.step :number="1">
            <x-form.field name="subject" label="Objet" required maxlength="255" />

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="request_type_id" label="Type" :options="$requestTypes" placeholder="Sélectionner" required x-model="type" />
                <x-form.select name="urgency_level_id" label="Urgence" :options="$urgencyLevels->pluck('name', 'id')" placeholder="Non précisée"
                               required-when="complete" x-on:change="suggest($event.target.value)" />
            </div>

            <x-form.complete-notice />

            <x-form.section title="Échéance" icon="clock" :fields="['due_at']">
                <div class="sm:max-w-xs">
                    <label for="due_at" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Date d’échéance <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
                    <input id="due_at" type="date" name="due_at" required x-model="dueDate" @input="touched = true" min="{{ now()->toDateString() }}"
                           class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                    @error('due_at')
                        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Proposée automatiquement d’après l’urgence tant que vous ne la modifiez pas.</p>
            </x-form.section>
        </x-form.step>

        {{-- Étape 2 : destinataires, description, pièces jointes --}}
        <x-form.step :number="2">
            <x-form.section title="Destinataires" icon="users" :fields="['recipients', 'recipients.*']">
                <x-recipient-picker :directory="$directory" />
                <p class="text-xs font-medium text-slate-600 dark:text-slate-400">NB : chaque destinataire est prévenu par notification dès l’envoi de la demande.</p>
            </x-form.section>

            <x-form.section title="Description" icon="document" :fields="['description']">
                <x-form.textarea name="description" label="Détail de la demande" rows="6" required-when="complete" />
            </x-form.section>

            <x-form.section title="Pièces jointes" icon="paperclip" :open="false" :fields="['attachments', 'attachments.*']">
                <x-form.attachments />
            </x-form.section>
        </x-form.step>
    </x-form.modal>
</x-layouts.app>
