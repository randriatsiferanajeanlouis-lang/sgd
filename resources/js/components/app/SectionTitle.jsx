import { cn } from '@/lib/utils';

/** Titre de section d'une fiche (équivalent de <x-section-title>) : pastille d'icône, intitulé, compteur facultatif. */
export default function SectionTitle({ id, Icon, count = null, className, children }) {
    return (
        <h2 id={id} className={cn('flex items-center gap-2.5 text-base font-bold text-univ-ink dark:text-white', className)}>
            <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-md bg-brand-50 text-brand-500 dark:bg-slate-800 dark:text-brand-300">
                <Icon className="size-4" aria-hidden="true" />
            </span>
            <span>{children}</span>
            {count !== null && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{count}</span>}
        </h2>
    );
}
