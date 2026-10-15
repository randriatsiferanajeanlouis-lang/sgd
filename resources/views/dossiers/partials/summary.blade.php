@php($dossierClosed = in_array($dossier->status, [\App\Enums\DossierStatus::Answered, \App\Enums\DossierStatus::Archived], true))

<div class="card">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-sm font-semibold text-brand-600 dark:text-brand-300">{{ $dossier->reference }}</span>
                <x-badge :color="$dossier->status->color()">{{ $dossier->status->label() }}</x-badge>
                <x-badge :color="$dossier->isExternal() ? 'violet' : 'slate'">{{ $dossier->isExternal() ? 'Demande externe' : 'Demande interne' }}</x-badge>
                <x-deadline :due-at="$dossier->due_at" :closed="$dossierClosed" />
            </div>
            <h1 class="mt-2 text-xl font-semibold break-words sm:text-2xl">{{ $dossier->subject }}</h1>
        </div>

        <div class="flex flex-wrap gap-2">
            @can('update', $dossier)
                <a href="{{ route('dossiers.edit', $dossier) }}" class="btn-secondary">Modifier</a>
            @endcan
        </div>
    </div>

    @php($deadline = \App\Support\Deadline::describe($dossier->due_at, $dossierClosed))
    @if ($deadline && $deadline['overdue'])
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert">
            Statut : {{ $dossier->status->label() }} — Alerte : délai dépassé.
        </div>
    @endif

    <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div><dt class="text-slate-500 dark:text-slate-400">Type</dt><dd class="font-medium">{{ $dossier->requestType->name }}</dd></div>
        <div><dt class="text-slate-500 dark:text-slate-400">Urgence</dt><dd class="font-medium">{{ $dossier->urgencyLevel?->name ?? '—' }}</dd></div>
        <div>
            <dt class="text-slate-500 dark:text-slate-400">{{ $dossier->isExternal() ? 'Enregistré par' : 'Expéditeur' }}</dt>
            <dd class="font-medium">{{ $dossier->creator->name }} <span class="font-normal text-slate-500">({{ $dossier->creator->department?->name }})</span></dd>
        </div>
        <div><dt class="text-slate-500 dark:text-slate-400">Échéance</dt><dd class="font-medium">{{ $dossier->due_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
        <div><dt class="text-slate-500 dark:text-slate-400">Créé le</dt><dd class="font-medium">{{ $dossier->created_at->format('d/m/Y H:i') }}</dd></div>
        @if ($dossier->isExternal())
            <div><dt class="text-slate-500 dark:text-slate-400">Reçu le</dt><dd class="font-medium">{{ $dossier->received_at?->format('d/m/Y') }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Canal</dt><dd class="font-medium">{{ $dossier->reception_channel?->label() ?? '—' }}</dd></div>
        @endif
    </dl>

    @if ($dossier->isExternal() && $dossier->externalRequester)
        @php($requester = $dossier->externalRequester)
        <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-800/50">
            <div class="font-semibold">Demandeur externe</div>
            <div class="mt-1">{{ $requester->full_name }}@if ($requester->organization) — {{ $requester->organization }}@endif</div>
            <div class="text-slate-500 dark:text-slate-400">{{ collect([$requester->phone, $requester->email])->filter()->implode(' · ') }}</div>
        </div>
    @endif

    @if ($dossier->description)
        <div class="mt-6">
            <div class="text-sm text-slate-500 dark:text-slate-400">Description</div>
            <div class="mt-1 whitespace-pre-line">{{ $dossier->description }}</div>
        </div>
    @endif
</div>
