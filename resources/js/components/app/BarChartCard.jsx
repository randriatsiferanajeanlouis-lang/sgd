import { Bar, BarChart, CartesianGrid, Cell, LabelList, XAxis, YAxis } from 'recharts';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { useTheme } from '@/hooks/use-theme';
import { resolveColor } from '@/lib/colors';

const palette = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];

/**
 * Histogramme d'une répartition : rows = [{ label, value, color? }].
 * horizontal : barres couchées (libellés longs, ex. départements).
 */
export default function BarChartCard({ title, description, rows, unit = 'dossier(s)', horizontal = false, height = 240, action, className }) {
    const { theme } = useTheme();
    const total = rows.reduce((sum, row) => sum + row.value, 0);
    const config = { value: { label: unit, color: 'var(--chart-1)' } };

    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
                <CardAction>{action ?? <span className="text-xs text-muted-foreground">Total <strong className="text-foreground">{total}</strong></span>}</CardAction>
            </CardHeader>
            <CardContent>
                {total === 0 ? (
                    <p className="grid place-items-center text-sm text-muted-foreground" style={{ height }}>Aucune donnée pour le moment.</p>
                ) : (
                    <ChartContainer config={config} className="aspect-auto w-full" style={{ height }}>
                        <BarChart data={rows} layout={horizontal ? 'vertical' : 'horizontal'} margin={{ left: horizontal ? 8 : -18, right: 24, top: 16 }}>
                            <CartesianGrid vertical={horizontal} horizontal={!horizontal} strokeDasharray="3 3" />
                            {horizontal ? (
                                <>
                                    <XAxis type="number" allowDecimals={false} tickLine={false} axisLine={false} />
                                    <YAxis type="category" dataKey="label" tickLine={false} axisLine={false} width={110} />
                                </>
                            ) : (
                                <>
                                    <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} />
                                    <YAxis allowDecimals={false} tickLine={false} axisLine={false} width={40} />
                                </>
                            )}
                            <ChartTooltip cursor={{ fillOpacity: 0.4 }} content={<ChartTooltipContent hideIndicator />} />
                            <Bar dataKey="value" radius={6} maxBarSize={48} isAnimationActive={false}>
                                {rows.map((row, index) => (
                                    <Cell key={row.label} fill={resolveColor(row.color, theme) ?? palette[index % palette.length]} />
                                ))}
                                <LabelList dataKey="value" position={horizontal ? 'right' : 'top'} className="fill-foreground text-xs font-semibold" />
                            </Bar>
                        </BarChart>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}
