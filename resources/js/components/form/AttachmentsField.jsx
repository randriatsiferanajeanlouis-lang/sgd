import { forwardRef } from 'react';

export const ATTACHMENT_TYPES = '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp';

/** Premier message d'erreur pour « attachments » ou l'un de ses fichiers (« attachments.0 »…). */
export function attachmentsError(errors, name = 'attachments') {
    return errors[name] ?? Object.entries(errors).find(([key]) => key.startsWith(`${name}.`))?.[1];
}

/**
 * Pièces jointes multiples (même rendu que <x-form.attachments>).
 * onChange reçoit la liste des fichiers choisis.
 */
const AttachmentsField = forwardRef(function AttachmentsField({ id = 'attachments', label = 'Pièces jointes', onChange, error }, ref) {
    return (
        <div>
            <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                {label}
            </label>
            <input
                ref={ref}
                id={id}
                type="file"
                name="attachments[]"
                multiple
                accept={ATTACHMENT_TYPES}
                onChange={(event) => onChange([...event.target.files])}
                className="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
            />
            <p className="mt-1.5 text-xs text-slate-500 dark:text-slate-400">PDF, Word, Excel ou images — 20 Mo maximum par fichier, 10 fichiers maximum.</p>
            {error && <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{error}</p>}
        </div>
    );
});

export default AttachmentsField;
