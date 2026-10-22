import { cn } from '@/lib/utils';

/** Couleurs des pastilles (mêmes teintes que le composant Blade <x-badge>). */
const COLORS = {
    slate: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    blue: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    green: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    red: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
    violet: 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200',
};

export default function StatusBadge({ color = 'slate', className, children, ...props }) {
    return (
        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap', COLORS[color] ?? COLORS.slate, className)} {...props}>
            {children}
        </span>
    );
}
