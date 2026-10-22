import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/** Fusionne des classes Tailwind sans conflit (utilitaire standard de shadcn/ui). */
export function cn(...inputs) {
    return twMerge(clsx(inputs));
}
