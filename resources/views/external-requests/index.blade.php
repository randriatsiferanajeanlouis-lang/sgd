<x-layouts.app title="Demandes externes">
    <x-page-header title="Demandes externes" subtitle="Toutes les demandes extérieures, enregistrées par le RH.">
        <x-slot:actions>
            @can('createExternal', App\Models\Dossier::class)
                <a href="{{ route('external-requests.create') }}" class="btn-primary"><x-icon name="plus" /> Nouvelle demande externe</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @include('dossiers.partials.filters')

    <x-dossier-table :dossiers="$dossiers" />
</x-layouts.app>
