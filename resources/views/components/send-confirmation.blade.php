@props([
    'title' => 'Confirmer l’envoi',
    'question' => 'Êtes-vous sûr de vouloir envoyer cette demande à',
    'confirmLabel' => 'Confirmer l’envoi',
])

{{--
    Fenêtre de confirmation avant un envoi. S'appuie sur l'état Alpine du
    formulaire parent (formColumns ou confirmSend) : confirming, confirmNames,
    confirmSubject, submitting, confirmSend(), cancelSend().
--}}
<div x-show="confirming" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="send-confirmation-title" aria-describedby="send-confirmation-question"
     @keydown.escape.window="confirming && cancelSend()">
    <div x-show="confirming" x-transition.opacity class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" @click="cancelSend()" aria-hidden="true"></div>

    <div x-show="confirming" x-transition.scale.95
         class="relative w-full max-w-md overflow-hidden rounded-2xl border border-univ-line bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-start gap-4 p-6">
            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-cyan-400 to-cyan-600 text-white shadow-lg shadow-cyan-500/30">
                <x-icon name="send" class="size-5" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 id="send-confirmation-title" class="text-lg font-bold text-univ-ink dark:text-white">{{ $title }}</h2>
                <p id="send-confirmation-question" class="mt-1.5 text-sm leading-relaxed text-univ-muted dark:text-slate-300">
                    {{ $question }} <strong class="font-semibold text-univ-ink dark:text-white" x-text="formatNames(confirmNames)"></strong> ?
                </p>
            </div>
        </div>

        <div class="space-y-3 border-t border-univ-line bg-univ-surface/60 px-6 py-4 text-sm dark:border-slate-800 dark:bg-slate-950/30">
            <div x-show="confirmSubject">
                <div class="text-[11px] font-bold tracking-[0.06em] text-univ-muted uppercase dark:text-slate-400">Objet</div>
                <div class="mt-0.5 font-medium break-words" x-text="confirmSubject"></div>
            </div>
            <div>
                <div class="text-[11px] font-bold tracking-[0.06em] text-univ-muted uppercase dark:text-slate-400">
                    <span x-text="confirmNames.length > 1 ? confirmNames.length + ' destinataires' : 'Destinataire'"></span>
                </div>
                <ul class="mt-1.5 flex flex-wrap gap-1.5">
                    <template x-for="name in confirmNames" :key="name">
                        <li class="inline-flex items-center gap-1.5 rounded-md border border-brand-200 bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-900/40 dark:text-brand-200">
                            <x-icon name="user" class="size-3.5" /> <span x-text="name"></span>
                        </li>
                    </template>
                </ul>
            </div>
            <p class="flex items-center gap-1.5 text-xs text-univ-muted dark:text-slate-400">
                <x-icon name="bell" class="size-3.5" /> Les destinataires seront prévenus par notification.
            </p>
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-univ-line px-6 py-4 sm:flex-row sm:justify-end dark:border-slate-800">
            <button type="button" class="btn-secondary" @click="cancelSend()" :disabled="submitting">Annuler</button>
            <button type="button" class="btn-primary" data-confirm-button @click="confirmSend()" :disabled="submitting">
                <svg x-show="submitting" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                <x-icon name="send" class="size-4" x-show="!submitting" />
                <span x-text="submitting ? 'Envoi en cours…' : @js($confirmLabel)">{{ $confirmLabel }}</span>
            </button>
        </div>
    </div>
</div>
