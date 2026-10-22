import StatusBadge from '@/components/app/StatusBadge';

/**
 * Échéance (équivalent de <x-deadline>) : pastille « Échéance dans 3 jours » /
 * « ⚠ Délai dépassé… », ou simple date grise quand le traitement est clos.
 * `deadline` vient de Laravel (App\Support\Deadline) : { label, color, overdue }.
 */
export default function DeadlineBadge({ deadline }) {
    if (!deadline) {
        return null;
    }

    if (!deadline.color) {
        return <span className="text-xs text-slate-500 dark:text-slate-400">{deadline.label}</span>;
    }

    return (
        <StatusBadge color={deadline.color}>
            {deadline.overdue && '⚠ '}
            {deadline.label}
        </StatusBadge>
    );
}
