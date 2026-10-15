<x-layouts.app :title="'Dossiers envoyés — '.$department->name">
    <x-page-header :title="'Dossiers envoyés à '.$department->name" subtitle="Tous les dossiers que vous avez transmis à ce département.">
        <x-slot:actions>
            <a href="{{ auth()->user()->can('viewAny', App\Models\Department::class) ? route('admin.departments.index') : route('directory.index') }}" class="btn-secondary">Retour aux départements</a>
        </x-slot:actions>
    </x-page-header>

    <x-dossier-table :dossiers="$dossiers" />
</x-layouts.app>
