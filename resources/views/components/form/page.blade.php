@props([
    'title', 'action', 'cancel' => null, 'method' => 'POST', 'submit' => 'Enregistrer', 'submitIcon' => 'check',
    'subtitle' => null, 'kicker' => null, 'id' => 'page-form', 'panels' => 2,
    'completeTypes' => [], 'type' => null, 'back' => null, 'backLabel' => 'Retour',
    'confirm' => false, 'confirmQuestion' => 'Êtes-vous sûr de vouloir envoyer cette demande à',
])

{{--
    Formulaire sur une page (sans fenêtre modale) : les étapes sont des colonnes
    numérotées (<x-form.panel :number="n">) affichées côte à côte sur ordinateur,
    l'une sous l'autre sur téléphone. La barre d'actions reste visible en bas.
    « completeTypes » : types de demande dont tous les champs deviennent obligatoires.
--}}
<div x-data="formColumns({{ (int) $panels }}, @js($completeTypes), @js((string) old('request_type_id', $type)), @js((bool) $confirm))"
     @submit.capture="validateAll($event)">
    @if ($back)
        <nav class="mb-4 flex items-center gap-2 text-sm" aria-label="Fil d’Ariane">
            <a href="{{ $back }}" class="inline-flex items-center gap-1.5 font-semibold text-brand-500 hover:underline dark:text-brand-300">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                {{ $backLabel }}
            </a>
            <span class="text-slate-300 dark:text-slate-600">/</span>
            <span class="font-semibold text-univ-ink dark:text-white" aria-current="page">{{ $title }}</span>
        </nav>
    @endif

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            @if ($kicker)
                <p class="kicker mb-1.5">{{ $kicker }}</p>
            @endif
            <h1 class="text-2xl leading-tight font-extrabold tracking-[-0.02em] text-univ-ink sm:text-[28px] dark:text-white">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-1.5 max-w-2xl text-sm text-univ-muted dark:text-slate-400">{{ $subtitle }}</p>
            @endif
        </div>

        {{-- Avancement : nombre de colonnes complètes --}}
        <div class="w-full max-w-xs rounded-xl border border-univ-line bg-white px-4 py-3 sm:w-64 dark:border-slate-800 dark:bg-slate-900" aria-live="polite">
            <div class="flex items-center justify-between text-sm">
                <span class="font-semibold">Avancement</span>
                <span class="tabular-nums text-univ-muted dark:text-slate-400"><span x-text="filled">0</span> / {{ $panels }} étapes</span>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-univ-line dark:bg-slate-800">
                <div class="h-full rounded-full bg-brand-500 transition-all duration-300 dark:bg-brand-400" :style="`width: ${filled / {{ $panels }} * 100}%`" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <form id="{{ $id }}" method="POST" action="{{ $action }}" novalidate {{ $attributes }}>
        @csrf
        @if ($confirm)
            {{-- Jeton à usage unique : le serveur refuse un second envoi du même formulaire. --}}
            <input type="hidden" name="_submission" value="{{ old('_submission', (string) Str::uuid()) }}">
        @endif
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif

        @if ($errors->any())
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert">
                <x-icon name="info" class="mt-0.5 size-5 shrink-0" />
                <span>Le formulaire contient des erreurs : vérifiez les champs signalés en rouge.</span>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-2">
            {{ $slot }}
        </div>

        {{-- Barre d'actions, toujours visible en bas de l'écran --}}
        <div class="sticky bottom-0 z-10 -mx-4 mt-6 border-t border-univ-line bg-white/90 px-4 py-3 backdrop-blur-md sm:-mx-6 sm:px-6 dark:border-slate-800 dark:bg-slate-950/85">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-univ-muted dark:text-slate-400">
                    <span x-show="recipientError" x-cloak class="flex items-center gap-1.5 text-sm font-semibold text-red-600 dark:text-red-400" role="alert">
                        <x-icon name="info" class="size-4" /> Aucun destinataire sélectionné : choisissez au moins une personne avant d’envoyer.
                    </span>
                    <span x-show="!blocked && !recipientError">Les champs marqués <span class="font-semibold text-red-600 dark:text-red-400">*</span> sont obligatoires.</span>
                    <span x-show="blocked" x-cloak class="font-medium text-amber-700 dark:text-amber-300" role="status">
                        Remplissez tous les champs marqués <span class="text-red-600 dark:text-red-400">*</span> des deux étapes pour envoyer.
                    </span>
                </p>
                <div class="flex w-full gap-3 sm:w-auto">
                    @if ($cancel)
                        <a href="{{ $cancel }}" class="btn-secondary flex-1 sm:flex-none">Annuler</a>
                    @endif
                    <button type="submit" :disabled="submitting" :aria-disabled="blocked.toString()" :class="blocked && 'opacity-60'" class="btn-primary flex-1 sm:flex-none">
                        <svg x-show="submitting" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                        <x-icon :name="$submitIcon" class="size-4" x-show="!submitting" /> <span x-text="submitting ? 'Envoi en cours…' : @js($submit)">{{ $submit }}</span>
                    </button>
                </div>
            </div>
        </div>
    </form>

    @if ($confirm)
        <x-send-confirmation :question="$confirmQuestion" />
    @endif
</div>
