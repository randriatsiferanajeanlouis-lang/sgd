import { Cell, Label, Pie, PieChart } from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { useTheme } from '@/hooks/use-theme';
import { resolveColor } from '@/lib/colors';

const palette = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];

/** Anneau de répartition avec le total au centre et une légende chiffrée. */
export default function DonutCard({ title, description, rows, centerLabel = 'au total', className }) {
    const { theme } = useTheme();
    const total = rows.reduce((sum, row) => sum + row.value, 0);
    const data = rows.map((row, index) => ({ ...row, fill: resolveColor(row.color, theme) ?? palette[index % palette.length] }));
    const config = Object.fromEntries(data.map((row) => [row.label, { label: row.label, color: row.fill }]));

    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardContent>
                {total === 0 ? (
                    <p className="grid h-[220px] place-items-center text-sm text-muted-foreground">Aucune donnée pour le moment.</p>
                ) : (
                    <>
                        <ChartContainer config={config} className="mx-auto aspect-square h-[200px]">
                            <PieChart>
                                <ChartTooltip cursor={false} content={<ChartTooltipContent nameKey="label" hideLabel />} />
                                <Pie data={data} dataKey="value" nameKey="label" innerRadius={58} outerRadius={84} strokeWidth={3} stroke="var(--card)" isAnimationActive={false}>
                                    {data.map((row) => (
                                        <Cell key={row.label} fill={row.fill} />
                                    ))}
                                    <Label
                                        content={({ viewBox }) =>
                                            viewBox?.cx ? (
                                                <text x={viewBox.cx} y={viewBox.cy} textAnchor="middle" dominantBaseline="middle">
                                                    <tspan x={viewBox.cx} y={viewBox.cy} className="fill-foreground text-2xl font-extrabold">{total}</tspan>
                                                    <tspan x={viewBox.cx} y={(viewBox.cy ?? 0) + 20} className="fill-muted-foreground text-xs">{centerLabel}</tspan>
                                                </text>
                                            ) : null
                                        }
                                    />
                                </Pie>
                            </PieChart>
                        </ChartContainer>
                        <ul className="mt-4 space-y-1.5 text-sm">
                            {data.map((row) => (
                                <li key={row.label} className="flex items-center gap-2">
                                    <span className="size-2.5 rounded-sm" style={{ background: row.fill }} aria-hidden="true" />
                                    <span className="flex-1 text-muted-foreground">{row.label}</span>
                                    <span className="font-semibold tabular-nums">{row.value}</span>
                                    <span className="w-10 text-right text-xs text-muted-foreground tabular-nums">{Math.round((row.value / total) * 100)} %</span>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
