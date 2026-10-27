import { Check, ChevronLeft, Info } from 'lucide-react';

/**
 * Formulaire affiché directement dans la page (même rendu que le composant
 * Blade <x-form.card>) : lien Retour, en-tête, bandeau d'erreurs, carte de
 * champs et barre d'actions toujours visible en bas de l'écran.
 * Le formulaire occupe toute la largeur de la zone principale (`wide` est conservé, sans effet).
 */
export default function FormPage({
    id = 'page-form',
    title,
    subtitle = null,
    kicker = null,
    cancelUrl = null,
    submitLabel = 'Enregistrer',
    SubmitIcon = Check,
    processing = false,
    hasErrors = false,
    onSubmit,
    before = null,
    after = null,
    wide = false,
    children,
}) {
    return (
        <div className="w-full">
            {cancelUrl && (
                <nav className="mb-4 flex items-center gap-2 text-sm" aria-label="Fil d’Ariane">
                    <a href={cancelUrl} className="inline-flex items-center gap-1.5 font-semibold text-brand-500 hover:underline dark:text-brand-300">
                        <ChevronLeft className="size-4" aria-hidden="true" />
                        Retour
                    </a>
                </nav>
            )}

            <div className="mb-6">
                {kicker && <p className="kicker mb-1.5">{kicker}</p>}
                <h1 id={`${id}-title`} className="text-2xl leading-tight font-extrabold tracking-[-0.02em] break-words text-univ-ink sm:text-[28px] dark:text-white">
                    {title}
                </h1>
                {subtitle && <p className="mt-1.5 max-w-2xl text-sm text-univ-muted dark:text-slate-400">{subtitle}</p>}
            </div>

            {before}

            <form id={id} onSubmit={onSubmit} aria-labelledby={`${id}-title`}>
                {hasErrors && (
                    <div className="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert">
                        <Info className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                        <span>Le formulaire contient des erreurs : vérifiez les champs signalés en rouge.</span>
                    </div>
                )}

                <div className="card space-y-5">{children}</div>

                {/* Barre d'actions, toujours visible en bas de l'écran */}
                <div className="sticky bottom-3 z-10 mt-6 rounded-xl border border-univ-line bg-white/90 px-4 py-3 shadow-lg shadow-slate-900/5 backdrop-blur-md sm:px-5 dark:border-slate-800 dark:bg-slate-900/90 dark:shadow-none">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-xs text-univ-muted dark:text-slate-400">
                            Les champs marqués <span className="font-semibold text-red-600 dark:text-red-400">*</span> sont obligatoires.
                        </p>
                        <div className="flex w-full gap-3 sm:w-auto">
                            {cancelUrl && (
                                <a href={cancelUrl} className="btn-secondary flex-1 sm:flex-none">
                                    Annuler
                                </a>
                            )}
                            <button type="submit" disabled={processing} className="btn-primary flex-1 sm:flex-none">
                                <SubmitIcon className="size-4" aria-hidden="true" /> {submitLabel}
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            {/* Formulaires indépendants (actions sur un compte…), placés sous le formulaire principal. */}
            {after && <div className="mt-8 space-y-5">{after}</div>}
        </div>
    );
}
