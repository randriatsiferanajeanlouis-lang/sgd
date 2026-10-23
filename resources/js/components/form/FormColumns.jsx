import { Check, ChevronLeft, Info, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Colonne numérotée d'un formulaire sur une page (équivalent de <x-form.panel>) :
 * badge « Complète » / « À compléter », cadre rouge si un de ses champs est en erreur.
 */
export function FormPanel({ number, title, description, done, hasError = false, children }) {
    return (
        <section data-step={number} aria-labelledby={`panel-${number}-title`} className={cn('card scroll-mt-24 overflow-hidden p-0', hasError && 'ring-2 ring-red-300 dark:ring-red-800')}>
            <header className="flex items-start gap-4 border-b border-univ-line bg-univ-surface/60 px-5 py-4 sm:px-6 dark:border-slate-800 dark:bg-slate-950/30">
                <span className={cn('grid size-10 shrink-0 place-items-center rounded-xl text-sm font-extrabold transition', done ? 'bg-emerald-500 text-white' : 'bg-brand-500 text-white dark:bg-brand-400 dark:text-slate-950')}>
                    {done ? <Check className="size-5" strokeWidth={3} aria-hidden="true" /> : number}
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-[11px] font-bold tracking-[0.08em] text-univ-muted uppercase dark:text-slate-400">Étape {number}</p>
                    <h2 id={`panel-${number}-title`} className="text-base font-bold text-univ-ink dark:text-white">
                        {title}
                    </h2>
                    {description && <p className="mt-0.5 text-xs text-univ-muted dark:text-slate-400">{description}</p>}
                </div>
                <span
                    className={cn(
                        'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
                        done ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
                    )}
                >
                    {done ? 'Complète' : 'À compléter'}
                </span>
            </header>

            <div className="space-y-5 p-5 sm:p-6">{children}</div>
        </section>
    );
}

/**
 * Formulaire sur une page en colonnes numérotées (équivalent de <x-form.page>) :
 * fil d'Ariane, en-tête, avancement des étapes, bandeau d'erreurs et barre
 * d'actions toujours visible en bas de l'écran.
 */
export default function FormColumns({
    id = 'page-form',
    title,
    kicker,
    subtitle,
    backUrl,
    backLabel = 'Retour',
    cancelUrl,
    panels,
    filled,
    blocked = false,
    recipientError = false,
    hasErrors = false,
    processing = false,
    submitLabel,
    SubmitIcon = Check,
    onSubmit,
    formRef,
    children,
}) {
    return (
        <div>
            {backUrl && (
                <nav className="mb-4 flex items-center gap-2 text-sm" aria-label="Fil d’Ariane">
                    <a href={backUrl} className="inline-flex items-center gap-1.5 font-semibold text-brand-500 hover:underline dark:text-brand-300">
                        <ChevronLeft className="size-4" aria-hidden="true" />
                        {backLabel}
                    </a>
                    <span className="text-slate-300 dark:text-slate-600">/</span>
                    <span className="font-semibold text-univ-ink dark:text-white" aria-current="page">
                        {title}
                    </span>
                </nav>
            )}

            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div className="min-w-0">
                    {kicker && <p className="kicker mb-1.5">{kicker}</p>}
                    <h1 className="text-2xl leading-tight font-extrabold tracking-[-0.02em] text-univ-ink sm:text-[28px] dark:text-white">{title}</h1>
                    {subtitle && <p className="mt-1.5 max-w-2xl text-sm text-univ-muted dark:text-slate-400">{subtitle}</p>}
                </div>

                {/* Avancement : nombre de colonnes complètes */}
                <div className="w-full max-w-xs rounded-xl border border-univ-line bg-white px-4 py-3 sm:w-64 dark:border-slate-800 dark:bg-slate-900" aria-live="polite">
                    <div className="flex items-center justify-between text-sm">
                        <span className="font-semibold">Avancement</span>
                        <span className="text-univ-muted tabular-nums dark:text-slate-400">
                            {filled} / {panels} étapes
                        </span>
                    </div>
                    <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-univ-line dark:bg-slate-800">
                        <div className="h-full rounded-full bg-brand-500 transition-all duration-300 dark:bg-brand-400" style={{ width: `${(filled / panels) * 100}%` }} />
                    </div>
                </div>
            </div>

            <form id={id} ref={formRef} noValidate onSubmit={onSubmit}>
                {hasErrors && (
                    <div className="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert">
                        <Info className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                        <span>Le formulaire contient des erreurs : vérifiez les champs signalés en rouge.</span>
                    </div>
                )}

                <div className="grid gap-6 xl:grid-cols-2">{children}</div>

                {/* Barre d'actions, toujours visible en bas de l'écran */}
                <div className="sticky bottom-0 z-10 -mx-4 mt-6 border-t border-univ-line bg-white/90 px-4 py-3 backdrop-blur-md sm:-mx-6 sm:px-6 dark:border-slate-800 dark:bg-slate-950/85">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-xs text-univ-muted dark:text-slate-400">
                            {recipientError ? (
                                <span className="flex items-center gap-1.5 text-sm font-semibold text-red-600 dark:text-red-400" role="alert">
                                    <Info className="size-4" aria-hidden="true" /> Aucun destinataire sélectionné : choisissez au moins une personne avant d’envoyer.
                                </span>
                            ) : blocked ? (
                                <span className="font-medium text-amber-700 dark:text-amber-300" role="status">
                                    Remplissez tous les champs marqués <span className="text-red-600 dark:text-red-400">*</span> des deux étapes pour envoyer.
                                </span>
                            ) : (
                                <span>
                                    Les champs marqués <span className="font-semibold text-red-600 dark:text-red-400">*</span> sont obligatoires.
                                </span>
                            )}
                        </p>
                        <div className="flex w-full gap-3 sm:w-auto">
                            {cancelUrl && (
                                <a href={cancelUrl} className="btn-secondary flex-1 sm:flex-none">
                                    Annuler
                                </a>
                            )}
                            <button type="submit" disabled={processing} aria-disabled={blocked} className={cn('btn-primary flex-1 sm:flex-none', blocked && 'opacity-60')}>
                                {processing ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : <SubmitIcon className="size-4" aria-hidden="true" />}
                                {processing ? 'Envoi en cours…' : submitLabel}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    );
}
