@php($editing = $department->exists)

<x-layouts.app :title="$editing ? 'Modifier un département' : 'Créer un département'">
    <x-form.modal :title="$editing ? 'Modifier le département '.$department->name : 'Créer un département'"
                  :action="$editing ? route('admin.departments.update', $department) : route('admin.departments.store')"
                  :method="$editing ? 'PUT' : 'POST'" :cancel="route('admin.departments.index')">

        <x-form.field name="name" label="Nom" :value="$department->name" required />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.field name="code" label="Code" :value="$department->code" required hint="Identifiant court, ex. PRODUCTION" />
            <x-form.field name="email" label="E-mail du département" type="email" :value="$department->email" />
        </div>

        @if ($editing)
            <x-form.section title="Responsable" icon="user" :fields="['manager_id']">
                <x-form.select name="manager_id" label="Responsable" :options="$members->mapWithKeys(fn ($member) => [$member->id => $member->name.($member->job_title ? ' — '.$member->job_title : '')])"
                    :value="$department->manager_id" placeholder="Aucun responsable" />
                <p class="text-xs font-medium text-slate-600 dark:text-slate-400">NB : le responsable choisi reçoit le rôle Responsable s’il était Employé.</p>
            </x-form.section>
        @endif

        <x-form.section title="Description" icon="document" :open="filled($department->description)" :fields="['description']">
            <x-form.textarea name="description" label="Présentation du département" :value="$department->description" rows="3" />
        </x-form.section>
    </x-form.modal>
</x-layouts.app>
