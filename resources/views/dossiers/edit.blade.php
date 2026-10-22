<x-layouts.app :title="'Modifier '.$dossier->reference">
    <x-form.card :title="'Modifier le dossier '.$dossier->reference" subtitle="Chaque modification est conservée dans l’historique (ancienne et nouvelle valeur) et les destinataires sont prévenus."
                  :type="$dossier->request_type_id" :action="route('dossiers.update', $dossier)" method="PUT" :cancel="route('dossiers.show', $dossier)" submit="Enregistrer">

        <x-form.field name="subject" label="Objet" :value="$dossier->subject" required maxlength="255" />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.select name="request_type_id" label="Type" :options="$requestTypes" :value="$dossier->request_type_id" required x-model="type" />
            <x-form.select name="urgency_level_id" label="Urgence" :options="$urgencyLevels" :value="$dossier->urgency_level_id"
                           :placeholder="$dossier->isExternal() ? null : 'Non précisée'" />
        </div>

        <x-form.other-request-type :value="$dossier->request_type_other" />

        <x-form.section title="Échéance" icon="clock" :fields="['due_at']">
            <div class="sm:max-w-xs">
                @if ($dossier->isExternal())
                    <div class="mb-1.5 text-sm font-medium text-slate-700 dark:text-slate-300">Échéance</div>
                    <div class="rounded-lg border border-dashed border-slate-300 px-3 py-2.5 text-sm dark:border-slate-700">{{ $dossier->due_at?->format('d/m/Y H:i') }} (calculée)</div>
                @else
                    <x-form.field name="due_at" label="Date d’échéance" type="date" :value="$dossier->due_at?->toDateString()" required min="{{ now()->toDateString() }}" />
                @endif
            </div>
        </x-form.section>

        <x-form.section title="Description" icon="document" :fields="['description']">
            <x-form.textarea name="description" label="Détail de la demande" :value="$dossier->description" rows="6" />
        </x-form.section>

        <x-form.section title="Autres informations" icon="info" :open="false" :fields="['comment']">
            <x-form.field name="comment" label="Motif de la modification (facultatif)" />
        </x-form.section>
    </x-form.card>
</x-layouts.app>
