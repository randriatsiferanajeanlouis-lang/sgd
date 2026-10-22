import { Head } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { Clock, FolderOpen, Hourglass, Inbox, TimerReset } from 'lucide-react';
import BarChartCard from '@/components/app/BarChartCard';
import DonutCard from '@/components/app/DonutCard';
import TrendChart from '@/components/app/TrendChart';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';

/**
 * Statistiques (React + shadcn/ui + Recharts). Données : StatisticsController.
 */
export default function Statistics({ scopeLabel, kpis, trends, monthly, dossierStatuses, origins, treatments, requestTypes, urgencies, departments }) {
    const kpiCards = [
        { label: 'Dossiers', value: kpis.dossiers, icon: FolderOpen, caption: 'Émis ou reçus' },
        { label: 'Traitements ouverts', value: kpis.open, icon: Inbox, caption: 'En attente de réponse' },
        { label: 'Délais dépassés', value: kpis.overdue, icon: Clock, caption: 'Échéance passée', danger: kpis.overdue > 0 },
        { label: 'Délai moyen de réponse', value: kpis.averageResponseHours !== null ? `${String(kpis.averageResponseHours).replace('.', ',')} h` : '—', icon: Hourglass, caption: `${kpis.answered} réponse(s)` },
    ];

    return (
        <>
            <Head title="Statistiques" />

            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="kicker mb-1">Indicateurs</p>
                    <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Statistiques</h1>
                    <p className="mt-1 text-sm text-muted-foreground">{scopeLabel}</p>
                </div>
            </div>

            <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="Indicateurs clés">
                {kpiCards.map((kpi) => (
                    <Card key={kpi.label} className="gap-3 py-5">
                        <CardContent className="flex items-center gap-4 px-5">
                            <span className={cn('grid size-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br text-white shadow-lg', kpi.danger ? 'from-rose-400 to-rose-600 shadow-rose-500/30' : 'from-cyan-400 to-cyan-600 shadow-cyan-500/30')}>
                                <kpi.icon className="size-5" aria-hidden="true" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-sm leading-snug font-semibold">{kpi.label}</p>
                                <p className={cn('text-2xl font-extrabold tabular-nums', kpi.danger && 'text-destructive')}>{kpi.value}</p>
                                <p className="text-xs text-muted-foreground">{kpi.caption}</p>
                            </div>
                        </CardContent>
                    </Card>
                ))}
                <Card className="gap-3 py-5">
                    <CardContent className="px-5">
                        <div className="flex items-center gap-2 text-sm font-semibold">
                            <TimerReset className="size-4 text-primary" aria-hidden="true" /> Réponses dans les délais
                        </div>
                        <p className="mt-1 text-2xl font-extrabold tabular-nums">{kpis.onTimeRate !== null ? `${kpis.onTimeRate} %` : '—'}</p>
                        <Progress value={kpis.onTimeRate ?? 0} className="mt-2" aria-label="Réponses dans les délais" />
                    </CardContent>
                </Card>
            </section>

            {trends && (
                <TrendChart
                    className="mt-6"
                    trends={trends}
                    useSeriesColors
                    title="Évolution des dossiers"
                    description={trends.periods.month.series.length === 1 ? 'Dossiers émis ou reçus par le département' : 'Dossiers émis ou reçus par chaque département'}
                    testId="stats-trend"
                />
            )}

            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                <MonthlyCard className="xl:col-span-2" rows={monthly} />
                <DonutCard title="Origine des dossiers" description="Demandes internes ou externes" rows={origins} />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
                <BarChartCard title="Dossiers par statut" rows={dossierStatuses} />
                <BarChartCard title="Traitements des destinataires" rows={treatments} unit="traitement(s)" />
                <DonutCard title="Niveaux d’urgence" description="Urgence choisie à la création" rows={urgencies} />
            </div>

            <BarChartCard className="mt-6" title="Types de demandes" description="Les plus fréquents" rows={requestTypes} horizontal height={Math.max(160, requestTypes.length * 40)} />

            {departments.length > 0 && (
                <>
                    <div className="mt-6 grid gap-6 lg:grid-cols-2">
                        <BarChartCard title="Dossiers par département" rows={departments.map((row) => ({ label: row.name, value: row.dossiers, color: row.color }))} horizontal height={departments.length * 44} />
                        <BarChartCard
                            title="Délai moyen de réponse"
                            description="En heures, par département destinataire"
                            unit="heure(s)"
                            action={<span className="text-xs text-muted-foreground">Moyenne</span>}
                            rows={departments.map((row) => ({ label: row.name, value: row.averageHours, color: row.color }))}
                            horizontal
                            height={departments.length * 44}
                        />
                    </div>

                    <Card className="mt-6 gap-0 pb-0">
                        <CardHeader className="pb-4">
                            <CardTitle>Par département</CardTitle>
                            <CardDescription>Charge et retards de chaque service</CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-6">Département</TableHead>
                                        <TableHead className="text-right">Dossiers</TableHead>
                                        <TableHead className="text-right">Traitements ouverts</TableHead>
                                        <TableHead className="text-right">Délais dépassés</TableHead>
                                        <TableHead className="pr-6 text-right">Délai moyen</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {departments.map((row) => (
                                        <TableRow key={row.code}>
                                            <TableCell className="pl-6 font-medium">{row.name}</TableCell>
                                            <TableCell className="text-right tabular-nums">{row.dossiers}</TableCell>
                                            <TableCell className="text-right tabular-nums">{row.open}</TableCell>
                                            <TableCell className={cn('text-right tabular-nums', row.overdue > 0 && 'font-semibold text-destructive')}>{row.overdue}</TableCell>
                                            <TableCell className="pr-6 text-right tabular-nums">{row.averageHours ? `${String(row.averageHours).replace('.', ',')} h` : '—'}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </>
            )}
        </>
    );
}

function MonthlyCard({ rows, className }) {
    const config = {
        internal: { label: 'Internes', color: 'var(--chart-1)' },
        external: { label: 'Externes', color: 'var(--chart-5)' },
    };

    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle>Volume mensuel</CardTitle>
                <CardDescription>Dossiers créés ces 12 derniers mois</CardDescription>
            </CardHeader>
            <CardContent>
                <ChartContainer config={config} className="aspect-auto h-[280px] w-full">
                    <BarChart data={rows} margin={{ left: -18, right: 8, top: 8 }}>
                        <CartesianGrid vertical={false} strokeDasharray="3 3" />
                        <XAxis dataKey="month" tickLine={false} axisLine={false} tickMargin={8} />
                        <YAxis allowDecimals={false} tickLine={false} axisLine={false} width={40} />
                        <ChartTooltip cursor={{ fillOpacity: 0.4 }} content={<ChartTooltipContent />} />
                        <ChartLegend content={<ChartLegendContent />} />
                        <Bar dataKey="internal" stackId="a" fill="var(--color-internal)" radius={[0, 0, 4, 4]} isAnimationActive={false} />
                        <Bar dataKey="external" stackId="a" fill="var(--color-external)" radius={[4, 4, 0, 0]} isAnimationActive={false} />
                    </BarChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}
