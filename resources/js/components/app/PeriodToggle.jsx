import { cn } from '@/lib/utils';

/** Sélecteur de période (Semaine / Mois / Année), style « pastilles » de la référence. */
export default function PeriodToggle({ periods, value, onChange }) {
    return (
        <div className="inline-flex rounded-lg border bg-muted/60 p-0.5" role="group" aria-label="Période">
            {Object.entries(periods).map(([key, period]) => (
                <button
                    key={key}
                    type="button"
                    onClick={() => onChange(key)}
                    aria-pressed={value === key}
                    className={cn(
                        'rounded-md px-2.5 py-1 text-xs font-semibold transition',
                        value === key ? 'bg-card text-foreground shadow-sm ring-1 ring-border' : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {period.label}
                </button>
            ))}
        </div>
    );
}
