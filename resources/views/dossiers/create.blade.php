<x-layouts.app title="Nouvelle demande">
    <x-form.page title="Nouvelle demande interne" kicker="Demande"
                 subtitle="Décrivez votre demande, fixez son échéance, puis choisissez les personnes qui doivent la traiter."
                 :action="route('dossiers.store')" :cancel="route('requests.index')" :back="route('requests.index')" back-label="Mes demandes"
                 submit="Envoyer la demande" submit-icon="send" :panels="2" confirm
                 :complete-types="$completeTypes"
                 enctype="multipart/form-data" :x-data="'dueDateHelper('.Js::from($urgencyLevels).', '.Js::from(old('due_at', '')).')'">

        {{-- Étape 1 : objet, type, urgence et échéance --}}
        <x-form.panel :number="1" title="Demande et échéance" description="Ce qui est demandé, son type, son urgence et la date de réponse attendue."
                      :fields="['subject', 'request_type_id', 'request_type_other', 'urgency_level_id', 'due_at']">
            <x-form.field name="subject" label="Objet" required maxlength="255" placeholder="Ex. : Demande de maintenance de la chaîne n°2" />

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="request_type_id" label="Type de demande" :options="$requestTypes" placeholder="Sélectionner" required x-model="type" />
                <x-form.select name="urgency_level_id" label="Urgence" :options="$urgencyLevels->pluck('name', 'id')" placeholder="Non précisée"
                               required-when="complete" x-on:change="suggest($event.target.value)" />
            </div>

            <x-form.other-request-type />

            <x-form.complete-notice />

            <div class="rounded-xl border border-univ-line bg-univ-surface/60 p-4 dark:border-slate-800 dark:bg-slate-950/30">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="w-full sm:w-56">
                        <label for="due_at" class="mb-1.5 flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                            <x-icon name="clock" class="size-4 text-brand-500 dark:text-brand-300" />
                            Date d’échéance <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                        </label>
                        <input id="due_at" type="date" name="due_at" required x-model="dueDate" @input="touched = true" min="{{ now()->toDateString() }}"
                               class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                    </div>
                    <p class="min-w-0 flex-1 text-xs text-univ-muted dark:text-slate-400">
                        Proposée automatiquement d’après l’urgence tant que vous ne la modifiez pas. Les destinataires sont alertés si elle est dépassée.
                    </p>
                </div>
                @error('due_at')
                    <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </x-form.panel>

        {{-- Étape 2 : destinataires, description, pièces jointes --}}
        <x-form.panel :number="2" title="Destinataires et description" description="Les personnes qui traiteront la demande, le détail et les pièces utiles."
                      :fields="['recipients', 'recipients.*', 'description', 'attachments', 'attachments.*']">
            <div class="space-y-2">
                <x-recipient-picker :directory="$directory" />
                <p class="flex items-center gap-1.5 text-xs text-univ-muted dark:text-slate-400">
                    <x-icon name="bell" class="size-3.5" /> Chaque destinataire est prévenu par notification dès l’envoi de la demande.
                </p>
            </div>

            <x-form.textarea name="description" label="Détail de la demande" rows="6" required-when="complete"
                             placeholder="Contexte, éléments attendus, contraintes éventuelles…" />

            <x-form.section title="Pièces jointes (facultatif)" icon="paperclip" :open="false" :fields="['attachments', 'attachments.*']">
                <x-form.attachments />
            </x-form.section>
        </x-form.panel>
    </x-form.page>
</x-layouts.app>
