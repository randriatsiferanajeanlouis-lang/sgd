import { Bell, Loader2, Send, UserRound } from 'lucide-react';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

/**
 * Fenêtre de confirmation avant un envoi ou une action importante (même
 * présentation que <x-send-confirmation>). Avec `names`, la question se
 * termine par le ou les destinataires et rappelle qu'ils seront prévenus.
 */
export default function ConfirmDialog({
    open,
    onOpenChange,
    title,
    question,
    names = null,
    subject = null,
    confirmLabel,
    onConfirm,
    processing = false,
    danger = false,
    Icon = Send,
}) {
    return (
        <Dialog open={open} onOpenChange={(value) => !processing && onOpenChange(value)}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md" showCloseButton={false}>
                <div className="flex items-start gap-4 p-6">
                    <span
                        className={cn(
                            'grid size-11 shrink-0 place-items-center rounded-xl text-white shadow-lg',
                            danger ? 'bg-gradient-to-br from-red-500 to-red-700 shadow-red-500/30' : 'bg-gradient-to-br from-cyan-400 to-cyan-600 shadow-cyan-500/30',
                        )}
                    >
                        <Icon className="size-5" aria-hidden="true" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <DialogTitle className="text-lg font-bold text-univ-ink dark:text-white">{title}</DialogTitle>
                        <DialogDescription className="mt-1.5 text-sm leading-relaxed text-univ-muted dark:text-slate-300">
                            {question}
                            {names && (
                                <>
                                    {' '}
                                    <strong className="font-semibold text-univ-ink dark:text-white">{names.join(', ')}</strong> ?
                                </>
                            )}
                        </DialogDescription>
                    </div>
                </div>

                {names && (
                    <div className="space-y-3 border-t border-univ-line bg-univ-surface/60 px-6 py-4 text-sm dark:border-slate-800 dark:bg-slate-950/30">
                        {subject && (
                            <div>
                                <div className="text-[11px] font-bold tracking-[0.06em] text-univ-muted uppercase dark:text-slate-400">Objet</div>
                                <div className="mt-0.5 font-medium break-words">{subject}</div>
                            </div>
                        )}
                        <div>
                            <div className="text-[11px] font-bold tracking-[0.06em] text-univ-muted uppercase dark:text-slate-400">
                                {names.length > 1 ? `${names.length} destinataires` : 'Destinataire'}
                            </div>
                            <ul className="mt-1.5 flex flex-wrap gap-1.5">
                                {names.map((name) => (
                                    <li
                                        key={name}
                                        className="inline-flex items-center gap-1.5 rounded-md border border-brand-200 bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-900/40 dark:text-brand-200"
                                    >
                                        <UserRound className="size-3.5" aria-hidden="true" /> {name}
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <p className="flex items-center gap-1.5 text-xs text-univ-muted dark:text-slate-400">
                            <Bell className="size-3.5" aria-hidden="true" /> Les destinataires seront prévenus par notification.
                        </p>
                    </div>
                )}

                <div className="flex flex-col-reverse gap-2 border-t border-univ-line px-6 py-4 sm:flex-row sm:justify-end dark:border-slate-800">
                    <button type="button" className="btn-secondary" onClick={() => onOpenChange(false)} disabled={processing}>
                        Annuler
                    </button>
                    <button type="button" autoFocus className={danger ? 'btn-danger' : 'btn-primary'} onClick={onConfirm} disabled={processing} data-confirm-button>
                        {processing ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : <Icon className="size-4" aria-hidden="true" />}
                        {processing ? 'Envoi en cours…' : confirmLabel}
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
