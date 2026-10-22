import { cn } from '@/lib/utils';

/**
 * Champ de formulaire (même rendu que le composant Blade <x-form.field>) :
 * libellé, astérisque rouge si obligatoire, aide et message d'erreur Laravel.
 */
export default function Field({ name, label, type = 'text', value, onChange, error, hint, required = false, className, inputClassName, ...props }) {
    return (
        <div className={className}>
            <label htmlFor={name} className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                {label}
                {required && (
                    <span className="text-red-600 dark:text-red-400" aria-hidden="true">
                        {' '}*
                    </span>
                )}
            </label>

            <input
                id={name}
                name={name}
                type={type}
                value={value ?? ''}
                onChange={(event) => onChange?.(event.target.value, event)}
                required={required}
                aria-invalid={error ? 'true' : undefined}
                aria-describedby={error ? `${name}-error` : hint ? `${name}-hint` : undefined}
                className={cn(
                    'block w-full rounded-lg border bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-brand-500/30 dark:bg-slate-900',
                    error ? 'border-red-400 focus:border-red-500' : 'border-slate-300 focus:border-brand-500 dark:border-slate-700',
                    inputClassName,
                )}
                {...props}
            />

            {hint && (
                <p id={`${name}-hint`} className="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                    {hint}
                </p>
            )}

            {error && (
                <p id={`${name}-error`} className="mt-1.5 text-sm text-red-600 dark:text-red-400">
                    {error}
                </p>
            )}
        </div>
    );
}
