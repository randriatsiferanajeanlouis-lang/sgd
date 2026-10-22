import { useEffect, useState } from 'react';
import { Minus, Plus } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Section repliable (+ / −), même rendu que <x-form.section> : elle s'ouvre
 * d'office dès que l'un de ses champs est en erreur.
 */
export default function FormSection({ title, Icon = null, defaultOpen = true, hasErrors = false, className, children }) {
    const [open, setOpen] = useState(defaultOpen || hasErrors);

    useEffect(() => {
        if (hasErrors) {
            setOpen(true);
        }
    }, [hasErrors]);

    return (
        <section
            className={cn('rounded-lg border border-slate-200 dark:border-slate-700', className)}
            // Champ obligatoire vide dans une section repliée : on l'ouvre tout de suite
            // pour que le navigateur puisse montrer le champ à compléter.
            onInvalidCapture={(event) => {
                event.currentTarget.querySelector('[hidden]')?.removeAttribute('hidden');
                setOpen(true);
            }}
        >
            <h2>
                <button
                    type="button"
                    onClick={() => setOpen(!open)}
                    aria-expanded={open}
                    className="flex w-full items-center justify-between gap-3 rounded-lg px-4 py-3 text-left text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800/60"
                >
                    <span className="flex items-center gap-2.5">
                        {Icon && <Icon className="size-4 text-slate-500 dark:text-slate-400" aria-hidden="true" />}
                        {title}
                    </span>
                    {open ? <Minus className="size-4" aria-hidden="true" /> : <Plus className="size-4" aria-hidden="true" />}
                </button>
            </h2>

            {/* Champs cachés mais toujours présents : la validation du navigateur les voit. */}
            <div hidden={!open} className="space-y-4 border-t border-slate-200 px-4 py-4 dark:border-slate-700">
                {children}
            </div>
        </section>
    );
}
