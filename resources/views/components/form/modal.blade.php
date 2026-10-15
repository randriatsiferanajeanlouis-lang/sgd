@props([
    'title', 'action', 'cancel' => null, 'method' => 'POST', 'submit' => 'Enregistrer', 'submitIcon' => 'check',
    'subtitle' => null, 'id' => 'modal-form', 'after' => null,
    'steps' => [], 'completeTypes' => [], 'type' => null,
])

{{--
    Formulaire présenté en fenêtre modale : titre et bouton d'envoi en tête, sections repliables dans le corps.
    Les boutons d'envoi visent le formulaire par son id : le slot « after » peut ainsi accueillir
    d'autres formulaires (actions indépendantes), qui ne peuvent pas être imbriqués dans le premier.

    Avec « steps » ([['title' => …, 'fields' => [...]], …]), le formulaire se remplit par étapes
    (<x-form.step :number="n">) : « Suivant » vérifie les champs de l'étape avant d'avancer.
    « completeTypes » : types de demande dont tous les champs deviennent obligatoires (variable « complete »).
--}}
@php
    $stepCount = count($steps);
    $startStep = 1;

    foreach ($steps as $index => $definition) {
        if (collect($definition['fields'] ?? [])->contains(fn ($field) => $errors->has($field))) {
            $startStep = $index + 1;
            break;
        }
    }
@endphp

<div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title"
     x-data="formSteps({{ max($stepCount, 1) }}, {{ $startStep }}, @js($completeTypes), @js((string) old('request_type_id', $type)))"
     x-init="document.body.classList.add('overflow-hidden')"
     @submit.capture="validateAll($event)">
    <div class="flex min-h-full items-start justify-center px-3 py-6 sm:px-6 sm:py-10">
        <div class="relative w-full max-w-3xl rounded-xl bg-white shadow-2xl dark:border dark:border-slate-700 dark:bg-slate-900">
            <div class="sticky top-0 z-10 rounded-t-xl border-b border-slate-200 bg-white/95 px-5 pt-5 pb-4 backdrop-blur sm:px-8 sm:pt-7 dark:border-slate-800 dark:bg-slate-900/95">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h1 id="{{ $id }}-title" class="text-xl font-semibold break-words sm:text-2xl">{{ $title }}</h1>
                        @if ($subtitle)
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @if ($stepCount > 1)
                            <button type="button" x-show="step < count" @click="next()" :disabled="blocked" class="btn-primary px-3 py-1.5">Suivant →</button>
                            <button type="submit" form="{{ $id }}" x-show="step === count" x-cloak :disabled="blocked" class="btn-success px-3 py-1.5"><x-icon :name="$submitIcon" class="size-4" /> {{ $submit }}</button>
                        @else
                            <button type="submit" form="{{ $id }}" class="btn-success px-3 py-1.5"><x-icon :name="$submitIcon" class="size-4" /> {{ $submit }}</button>
                        @endif
                        @if ($cancel)
                            <a href="{{ $cancel }}" class="inline-flex size-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800 dark:hover:text-slate-100" aria-label="Fermer sans enregistrer">
                                <x-icon name="x" />
                            </a>
                        @endif
                    </div>
                </div>

                @if ($stepCount > 1)
                    {{-- Indicateur d'étapes : une étape déjà validée peut être rouverte d'un clic --}}
                    <ol class="mt-4 flex flex-wrap items-center gap-2 text-sm" aria-label="Étapes du formulaire">
                        @foreach ($steps as $index => $definition)
                            @php($number = $index + 1)
                            <li class="flex items-center gap-2">
                                <button type="button" @click="goTo({{ $number }})" :aria-current="step === {{ $number }} ? 'step' : null"
                                        :class="step === {{ $number }} ? 'bg-brand-500 text-white shadow-sm' : (step > {{ $number }} ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400')"
                                        class="inline-flex items-center gap-2 rounded-full px-3 py-1 font-medium transition">
                                    <span class="inline-flex size-5 items-center justify-center rounded-full bg-white/25 text-xs font-bold"
                                          x-text="step > {{ $number }} ? '✓' : '{{ $number }}'">{{ $number }}</span>
                                    {{ $definition['title'] }}
                                </button>
                                @unless ($loop->last)
                                    <span class="h-px w-6 bg-slate-300 dark:bg-slate-700" aria-hidden="true"></span>
                                @endunless
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            <div class="space-y-5 px-5 py-6 sm:px-8">
                <form id="{{ $id }}" method="POST" action="{{ $action }}" {{ $attributes->class('space-y-5') }}>
                    @csrf
                    @if (strtoupper($method) !== 'POST')
                        @method($method)
                    @endif

                    <x-flash />

                    @if ($errors->any())
                        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert">
                            Le formulaire contient des erreurs : vérifiez les champs signalés en rouge.
                        </div>
                    @endif

                    {{ $slot }}
                </form>

                {{ $after }}
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-b-xl border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-8 dark:border-slate-800 dark:bg-slate-900">
                <div>
                    @if ($stepCount > 1)
                        <span class="text-sm text-slate-500 dark:text-slate-400">Étape <span x-text="step">{{ $startStep }}</span> sur {{ $stepCount }}</span>
                        <p x-show="blocked" x-cloak class="mt-0.5 text-xs font-medium text-amber-700 dark:text-amber-300" role="status">
                            Remplissez tous les champs marqués <span class="text-red-600 dark:text-red-400">*</span> pour continuer.
                        </p>
                    @endif
                </div>

                <div class="flex gap-3">
                    @if ($stepCount > 1)
                        <button type="button" x-show="step > 1" x-cloak @click="previous()" class="btn-secondary">← Précédent</button>
                    @endif
                    @if ($cancel)
                        <a href="{{ $cancel }}" class="btn-secondary">Annuler</a>
                    @endif
                    @if ($stepCount > 1)
                        <button type="button" x-show="step < count" @click="next()" :disabled="blocked" class="btn-primary">Suivant →</button>
                        <button type="submit" form="{{ $id }}" x-show="step === count" x-cloak :disabled="blocked" class="btn-success"><x-icon :name="$submitIcon" class="size-4" /> {{ $submit }}</button>
                    @else
                        <button type="submit" form="{{ $id }}" class="btn-success"><x-icon :name="$submitIcon" class="size-4" /> {{ $submit }}</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
