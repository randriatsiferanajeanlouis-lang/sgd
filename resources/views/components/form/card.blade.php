@props([
    'title', 'action', 'cancel' => null, 'method' => 'POST', 'submit' => 'Enregistrer', 'submitIcon' => 'check',
    'subtitle' => null, 'kicker' => null, 'id' => 'page-form', 'after' => null,
    'completeTypes' => [], 'type' => null,
])

{{--
    Formulaire affiché directement dans la page (sans fenêtre modale) : en-tête,
    carte de champs, barre d'actions toujours visible en bas. Le slot « after »
    accueille des formulaires indépendants (actions sur un compte…), affichés
    sous le formulaire principal car ils ne peuvent pas y être imbriqués.
    « type » / « completeTypes » : variables Alpine « type » et « complete »
    (type de demande « Autre », formulaire complet).
--}}
<div class="max-w-4xl">
    @if ($cancel)
        <nav class="mb-4 flex items-center gap-2 text-sm" aria-label="Fil d’Ariane">
            <a href="{{ $cancel }}" class="inline-flex items-center gap-1.5 font-semibold text-brand-500 hover:underline dark:text-brand-300">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                Retour
            </a>
        </nav>
    @endif

    <div class="mb-6">
        @if ($kicker)
            <p class="kicker mb-1.5">{{ $kicker }}</p>
        @endif
        <h1 id="{{ $id }}-title" class="text-2xl leading-tight font-extrabold tracking-[-0.02em] break-words text-univ-ink sm:text-[28px] dark:text-white">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1.5 max-w-2xl text-sm text-univ-muted dark:text-slate-400">{{ $subtitle }}</p>
        @endif
    </div>

    <div x-data="formColumns(0, @js($completeTypes), @js((string) old('request_type_id', $type)))" @submit.capture="validateAll($event)">
    <form id="{{ $id }}" method="POST" action="{{ $action }}" aria-labelledby="{{ $id }}-title" {{ $attributes }}>
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif

        @if ($errors->any())
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert">
                <x-icon name="info" class="mt-0.5 size-5 shrink-0" />
                <span>Le formulaire contient des erreurs : vérifiez les champs signalés en rouge.</span>
            </div>
        @endif

        <div class="card space-y-5">
            {{ $slot }}
        </div>

        {{-- Barre d'actions, toujours visible en bas de l'écran --}}
        <div class="sticky bottom-3 z-10 mt-6 rounded-xl border border-univ-line bg-white/90 px-4 py-3 shadow-lg shadow-slate-900/5 backdrop-blur-md sm:px-5 dark:border-slate-800 dark:bg-slate-900/90 dark:shadow-none">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-univ-muted dark:text-slate-400">Les champs marqués <span class="font-semibold text-red-600 dark:text-red-400">*</span> sont obligatoires.</p>
                <div class="flex w-full gap-3 sm:w-auto">
                    @if ($cancel)
                        <a href="{{ $cancel }}" class="btn-secondary flex-1 sm:flex-none">Annuler</a>
                    @endif
                    <button type="submit" :disabled="submitting" class="btn-primary flex-1 sm:flex-none">
                        <x-icon :name="$submitIcon" class="size-4" /> {{ $submit }}
                    </button>
                </div>
            </div>
        </div>
    </form>
    </div>

    @if ($after)
        <div class="mt-8 space-y-5">
            {{ $after }}
        </div>
    @endif
</div>
