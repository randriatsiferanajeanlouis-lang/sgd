import { Area, AreaChart } from 'recharts';
import { ArrowUpRight } from 'lucide-react';
import { ChartContainer } from '@/components/ui/chart';
import { cn } from '@/lib/utils';
import AppIcon from './AppIcon';

const tones = {
    cyan: { tile: 'from-cyan-400 to-cyan-600 shadow-cyan-500/30', stroke: 'var(--chart-1)' },
    rose: { tile: 'from-rose-400 to-rose-600 shadow-rose-500/30', stroke: 'var(--chart-2)' },
};

/**
 * Tuile chiffrée du tableau de bord : pastille d'icône, valeur, variation du
 * mois et mini-courbe des derniers mois (comme l'image de référence).
 */
export default function StatCard({ stat, tone = 'cyan' }) {
    const colors = tones[tone] ?? tones.cyan;
    const config = { value: { label: stat.label, color: colors.stroke } };
    const gradientId = `spark-${stat.key}`;

    return (
        <a
            href={stat.url}
            className="group relative flex flex-col gap-3 overflow-hidden rounded-xl border bg-card p-5 text-card-foreground shadow-xs transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg"
        >
            <div className="flex items-center gap-3">
                <span className={cn('grid size-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br text-white shadow-lg', colors.tile)}>
                    <AppIcon name={stat.icon} className="size-5" />
                </span>
                <p className="min-w-0 truncate text-sm font-semibold">{stat.label}</p>
            </div>
            <div className="flex items-end justify-between gap-3">
                <p className="text-3xl leading-none font-extrabold tracking-tight tabular-nums">{stat.value}</p>
                <ChartContainer config={config} className="aspect-auto h-11 w-28 shrink-0" aria-hidden="true">
                    <AreaChart data={stat.spark} margin={{ top: 4, right: 2, bottom: 2, left: 2 }} accessibilityLayer={false}>
                        <defs>
                            <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor="var(--color-value)" stopOpacity={0.35} />
                                <stop offset="100%" stopColor="var(--color-value)" stopOpacity={0} />
                            </linearGradient>
                        </defs>
                        <Area dataKey="value" type="monotone" stroke="var(--color-value)" strokeWidth={2} fill={`url(#${gradientId})`} isAnimationActive={false} />
                    </AreaChart>
                </ChartContainer>
            </div>
            <div className="text-xs text-muted-foreground">
                <span className={cn('inline-flex items-center gap-0.5 font-semibold', stat.thisMonth > 0 ? 'text-emerald-600 dark:text-emerald-400' : '')}>
                    {stat.thisMonth > 0 && <ArrowUpRight className="size-3.5" />}
                    {stat.thisMonth > 0 ? `+${stat.thisMonth}` : '0'} ce mois-ci
                </span>
                <span className="block">{stat.caption}</span>
            </div>
        </a>
    );
}
