<x-layouts.app title="Paramètres">
    <x-page-header title="Paramètres" subtitle="Toutes les modifications sont tracées dans le journal d’audit." />

    @php($inputClass = 'rounded-lg border border-slate-300 bg-white px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900')

    <div x-data="{ tab: new URLSearchParams(location.search).get('tab') || 'types' }">
        <div class="mb-6 flex flex-wrap gap-2" role="tablist">
            @foreach (['types' => 'Types de demandes', 'urgencies' => 'Urgences', 'permissions' => 'Rôles et permissions', 'general' => 'Réglages généraux'] as $key => $label)
                <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'btn-primary' : 'btn-secondary'">{{ $label }}</button>
            @endforeach
        </div>

        {{-- Types de demandes --}}
        <section x-show="tab === 'types'" class="space-y-6">
            @can('manage_request_types')
                <form method="POST" action="{{ route('admin.settings.request-types.store') }}" class="card flex flex-wrap items-end gap-4">
                    @csrf
                    <div class="w-full flex-1 sm:min-w-64"><x-form.field name="name" label="Nouveau type" required /></div>
                    <div class="w-full sm:w-56"><x-form.select name="scope" label="Portée" :options="$scopes" value="both" /></div>
                    <button type="submit" class="btn-primary py-2.5">Ajouter</button>
                </form>
            @endcan

            <div class="card overflow-x-auto p-0">
                <table class="data-table">
                    <thead><tr><th>Type</th><th>Portée</th><th title="Tous les champs du formulaire deviennent obligatoires (ex. Sponsoring)">Formulaire complet</th><th>Actif</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($requestTypes as $type)
                            @php($formId = 'request-type-'.$type->id)
                            <tr>
                                <td data-label="Type"><input form="{{ $formId }}" name="name" value="{{ $type->name }}" class="{{ $inputClass }} w-full" aria-label="Nom du type"></td>
                                <td data-label="Portée">
                                    <select form="{{ $formId }}" name="scope" class="{{ $inputClass }}" aria-label="Portée">
                                        @foreach ($scopes as $value => $label)
                                            <option value="{{ $value }}" @selected($type->scope->value === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td data-label="Formulaire complet"><input form="{{ $formId }}" type="checkbox" name="requires_complete_form" value="1" @checked($type->requires_complete_form) class="size-4" aria-label="Formulaire complet obligatoire"></td>
                                <td data-label="Actif"><input form="{{ $formId }}" type="checkbox" name="is_active" value="1" @checked($type->is_active) class="size-4" aria-label="Actif"></td>
                                <td class="text-right">
                                    @can('manage_request_types')
                                        <form id="{{ $formId }}" method="POST" action="{{ route('admin.settings.request-types.update', $type) }}">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn-secondary px-3 py-1.5">Enregistrer</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Urgences --}}
        <section x-show="tab === 'urgencies'" x-cloak class="card overflow-x-auto p-0">
            <table class="data-table">
                <thead><tr><th>Niveau</th><th>Délai de réponse (heures)</th><th>Actif</th><th></th></tr></thead>
                <tbody>
                    @foreach ($urgencyLevels as $level)
                        @php($formId = 'urgency-'.$level->id)
                        <tr>
                            <td data-label="Niveau"><input form="{{ $formId }}" name="name" value="{{ $level->name }}" class="{{ $inputClass }}" aria-label="Nom"></td>
                            <td data-label="Délai (heures)">
                                <input form="{{ $formId }}" type="number" min="1" name="response_delay_hours" value="{{ $level->response_delay_hours }}" class="{{ $inputClass }} w-28" aria-label="Délai en heures">
                                <span class="ml-2 text-xs text-slate-500">= {{ $level->response_delay_hours % 24 === 0 ? ($level->response_delay_hours / 24).' jour(s) calendaire(s)' : $level->response_delay_hours.' h' }}</span>
                            </td>
                            <td data-label="Actif"><input form="{{ $formId }}" type="checkbox" name="is_active" value="1" @checked($level->is_active) class="size-4" aria-label="Actif"></td>
                            <td class="text-right">
                                @can('manage_urgencies')
                                    <form id="{{ $formId }}" method="POST" action="{{ route('admin.settings.urgency-levels.update', $level) }}">
                                        @csrf
                                        @method('PUT')
                                        <button type="submit" class="btn-secondary px-3 py-1.5">Enregistrer</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        {{-- Permissions --}}
        <section x-show="tab === 'permissions'" x-cloak class="space-y-6">
            @foreach ($roles as $role)
                <form method="POST" action="{{ route('admin.settings.roles.permissions', $role) }}" class="card">
                    @csrf
                    @method('PUT')
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold">{{ $role->label }}</h2>
                        @can('manage_permissions')<button type="submit" class="btn-primary">Enregistrer</button>@endcan
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $role->description }}</p>
                    <div class="mt-4 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($permissionGroups as $group => $permissions)
                            <fieldset>
                                <legend class="mb-2 text-sm font-semibold">{{ $group }}</legend>
                                @foreach ($permissions as $permission)
                                    <label class="flex items-start gap-2 py-0.5 text-sm">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" class="mt-0.5 size-4"
                                               @checked($role->permissions->contains('id', $permission->id))>
                                        <span>{{ $permission->label }} <span class="font-mono text-xs text-slate-400">{{ $permission->name }}</span></span>
                                    </label>
                                @endforeach
                            </fieldset>
                        @endforeach
                    </div>
                </form>
            @endforeach
        </section>

        {{-- Réglages généraux --}}
        <section x-show="tab === 'general'" x-cloak class="card overflow-x-auto p-0">
            <table class="data-table">
                <thead><tr><th>Paramètre</th><th>Valeur</th><th></th></tr></thead>
                <tbody>
                    @foreach ($settings as $setting)
                        @php($formId = 'setting-'.$setting->id)
                        <tr>
                            <td data-label="Paramètre">{{ $setting->label ?? $setting->key }} <div class="font-mono text-xs text-slate-400">{{ $setting->key }}</div></td>
                            <td data-label="Valeur"><input form="{{ $formId }}" name="value" value="{{ $setting->value }}" class="{{ $inputClass }} w-full" aria-label="Valeur"></td>
                            <td class="text-right">
                                @can('manage_settings')
                                    <form id="{{ $formId }}" method="POST" action="{{ route('admin.settings.values.update', $setting) }}">
                                        @csrf
                                        @method('PUT')
                                        <button type="submit" class="btn-secondary px-3 py-1.5">Enregistrer</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>
</x-layouts.app>
