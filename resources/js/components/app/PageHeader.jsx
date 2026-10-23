/** En-tête de page (équivalent de <x-page-header>) : titre, sous-titre, actions à droite. */
export default function PageHeader({ title, subtitle = null, kicker = null, actions = null }) {
    return (
        <div className="mb-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-4 border-b border-univ-line pb-5 dark:border-slate-800">
            <div className="min-w-0">
                {kicker && <p className="kicker mb-2">{kicker}</p>}
                <h1 className="text-[22px] leading-tight font-extrabold tracking-[-0.025em] break-words text-univ-ink sm:text-[28px] dark:text-white">{title}</h1>
                {subtitle && <p className="mt-2 max-w-3xl text-sm leading-relaxed text-univ-muted dark:text-slate-400">{subtitle}</p>}
            </div>

            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </div>
    );
}
