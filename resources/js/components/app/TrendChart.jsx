import { useMemo, useState } from 'react';
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { cn } from '@/lib/utils';
import PeriodToggle from './PeriodToggle';

const palette = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];

/**
 * Courbes d'évolution (structure produite par App\Services\DossierTrends :
 * périodes week / month / year, chacune avec ses libellés et ses séries).
 */
export default function TrendChart({ trends, title, description, testId, useSeriesColors = false, className }) {
    const [period, setPeriod] = useState('month');
    const current = trends.periods[period];

    const { data, config } = useMemo(() => {
        const config = Object.fromEntries(
            current.series.map((serie, index) => [
                `s${index}`,
                useSeriesColors && serie.color
                    ? // Couleur propre à chaque département, déclinée pour le thème clair et sombre.
                      { label: `${serie.name} (${serie.total})`, ...(typeof serie.color === 'string' ? { color: serie.color } : { theme: serie.color }) }
                    : { label: `${serie.name} (${serie.total})`, color: palette[index % palette.length] },
            ]),
        );
        const data = current.labels.map((label, i) => ({
            label,
            ...Object.fromEntries(current.series.map((serie, index) => [`s${index}`, serie.data[i] ?? 0])),
        }));

        return { data, config };
    }, [current, useSeriesColors]);

    return (
        <Card className={cn('h-full', className)} data-testid={testId}>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
                <CardAction>
                    <PeriodToggle periods={trends.periods} value={period} onChange={setPeriod} />
                </CardAction>
            </CardHeader>
            <CardContent className="flex-1">
                <ChartContainer config={config} className="aspect-auto h-[280px] w-full">
                    <AreaChart data={data} margin={{ left: -18, right: 8, top: 8 }}>
                        <defs>
                            {Object.keys(config).map((key) => (
                                <linearGradient key={key} id={`fill-${testId}-${key}`} x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor={`var(--color-${key})`} stopOpacity={0.3} />
                                    <stop offset="95%" stopColor={`var(--color-${key})`} stopOpacity={0.02} />
                                </linearGradient>
                            ))}
                        </defs>
                        <CartesianGrid vertical={false} strokeDasharray="3 3" />
                        <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} minTickGap={24} />
                        <YAxis allowDecimals={false} tickLine={false} axisLine={false} width={40} />
                        <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="line" />} />
                        {Object.keys(config).map((key) => (
                            <Area
                                key={key}
                                dataKey={key}
                                type="monotone"
                                stroke={`var(--color-${key})`}
                                strokeWidth={2}
                                fill={`url(#fill-${testId}-${key})`}
                                dot={false}
                                isAnimationActive={false}
                                activeDot={{ r: 4 }}
                            />
                        ))}
                        <ChartLegend content={<ChartLegendContent />} />
                    </AreaChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}
