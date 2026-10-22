{{-- Vue racine Inertia : la page React s'affiche dans la mise en page Blade habituelle. --}}
<x-layouts.app :title="$page['props']['title'] ?? config('app.name')" :flash="false">
    @push('head')
        @vite('resources/js/inertia.jsx')
        @inertiaHead
    @endpush

    @inertia
</x-layouts.app>
