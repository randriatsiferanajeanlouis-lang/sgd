<x-layouts.app title="Dossiers du département">
    <x-page-header title="Dossiers du département" :subtitle="$department?->name" />

    @include('dossiers.partials.filters')

    <x-dossier-table :dossiers="$dossiers" />
</x-layouts.app>
