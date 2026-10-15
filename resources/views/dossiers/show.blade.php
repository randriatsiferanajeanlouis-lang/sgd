<x-layouts.app :title="$dossier->reference">
    @include('dossiers.partials.summary')

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @include('dossiers.partials.external-actions')
            @include('dossiers.partials.my-treatment')
            @include('dossiers.partials.validations')
            @include('dossiers.partials.recipients')
            @include('dossiers.partials.responses')
        </div>

        <div class="space-y-6">
            @include('dossiers.partials.documents')
        </div>
    </div>

    @if ($canViewHistory)
        <div class="mt-6">
            @include('dossiers.partials.history')
        </div>
    @endif
</x-layouts.app>
