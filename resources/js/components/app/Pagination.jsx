import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Pagination d'une liste Laravel (paginate()) envoyée à React : résumé et
 * numéros de page. Les liens gardent les filtres (withQueryString côté Laravel).
 */
export default function Pagination({ paginator }) {
    const { links = [], from, to, total, last_page: lastPage } = paginator;

    if (!lastPage || lastPage <= 1) {
        return null;
    }

    const pages = links.slice(1, -1);
    const previous = links[0];
    const next = links[links.length - 1];
    const base = 'inline-flex min-w-9 items-center justify-center rounded-lg border px-3 py-1.5 text-sm font-medium';
    const idle = 'border-univ-line bg-white text-univ-ink hover:border-brand-300 hover:bg-brand-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';

    const item = (link, label, key) =>
        link.url ? (
            <Link key={key} href={link.url} preserveScroll className={cn(base, link.active ? 'border-brand-500 bg-brand-500 text-white' : idle)} aria-current={link.active ? 'page' : undefined}>
                {label}
            </Link>
        ) : (
            <span key={key} className={cn(base, 'border-univ-line text-slate-400 dark:border-slate-800')} aria-disabled="true">
                {label}
            </span>
        );

    return (
        <nav className="flex flex-wrap items-center justify-between gap-3" aria-label="Pagination">
            <p className="text-sm text-univ-muted dark:text-slate-400">
                Affichage de <strong>{from}</strong> à <strong>{to}</strong> sur <strong>{total}</strong> résultats
            </p>
            <div className="flex flex-wrap gap-1.5">
                {item(previous, <><ChevronLeft className="size-4" aria-hidden="true" /> <span className="sr-only sm:not-sr-only">Précédent</span></>, 'prev')}
                {pages.map((link, index) => item(link, link.label, `p${index}`))}
                {item(next, <><span className="sr-only sm:not-sr-only">Suivant</span> <ChevronRight className="size-4" aria-hidden="true" /></>, 'next')}
            </div>
        </nav>
    );
}
