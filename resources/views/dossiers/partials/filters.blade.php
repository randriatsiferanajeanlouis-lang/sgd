<form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
    @isset($box)
        <input type="hidden" name="box" value="{{ $box }}">
    @endisset
    @if (! empty($selectedDepartment))
        <input type="hidden" name="department" value="{{ $selectedDepartment }}">
    @endif
    <div class="w-full sm:w-72">
        <x-form.search-field label="Rechercher" :value="request('search')" placeholder="Référence, objet ou expéditeur" />
    </div>
    <div class="w-full sm:w-48">
        <x-form.select name="status" label="Statut du dossier" :value="request('status')" placeholder="Tous"
            :options="collect(\App\Enums\DossierStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])" />
    </div>
    <button type="submit" class="btn-secondary py-2.5">Filtrer</button>
</form>
