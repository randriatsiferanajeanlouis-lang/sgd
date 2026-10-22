import { cn } from '@/lib/utils';

/** Zone de texte (même rendu que <x-form.textarea>) : libellé, astérisque si obligatoire, erreur Laravel. */
export default function TextareaField({ id, name, label, value, onChange, error, required = false, rows = 4, className, ...props }) {
    const fieldId = id ?? name;

    return (
        <div className={className}>
            <label htmlFor={fieldId} className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                {label}
                {required && (
                    <span className="text-red-600 dark:text-red-400" aria-hidden="true">
                        {' '}*
                    </span>
                )}
            </label>
            <textarea
                id={fieldId}
                name={name}
                rows={rows}
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                required={required}
                aria-invalid={error ? 'true' : undefined}
                className={cn(
                    'block w-full rounded-lg border bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-brand-500/30 dark:bg-slate-900',
                    error ? 'border-red-400' : 'border-slate-300 focus:border-brand-500 dark:border-slate-700',
                )}
                {...props}
            />
            {error && <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{error}</p>}
        </div>
    );
}
