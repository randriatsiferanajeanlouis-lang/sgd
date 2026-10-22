import { cn } from '@/lib/utils';

/**
 * Photo de l'utilisateur ou, à défaut, ses initiales (même rendu que le
 * composant Blade <x-avatar>). La photo est servie par Laravel aux seuls
 * utilisateurs connectés (route users.photo).
 */
export default function UserAvatar({ photoUrl = null, initials, size = 'size-9', className }) {
    if (photoUrl) {
        return <img src={photoUrl} alt="" className={cn(size, 'rounded-full object-cover', className)} />;
    }

    return (
        <span
            className={cn(
                size,
                'inline-flex items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700 dark:bg-brand-900 dark:text-brand-100',
                className,
            )}
        >
            {initials}
        </span>
    );
}
