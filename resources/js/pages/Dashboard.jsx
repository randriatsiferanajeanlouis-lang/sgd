import { Head } from '@inertiajs/react';
import { BarChart3, ChevronRight, Settings, Sparkles, UserPlus, Users } from 'lucide-react';
import ActivityList from '@/components/app/ActivityList';
import AppIcon from '@/components/app/AppIcon';
import BarChartCard from '@/components/app/BarChartCard';
import Greeting from '@/components/app/Greeting';
import StatCard from '@/components/app/StatCard';
import TrendChart from '@/components/app/TrendChart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';

const statusColors = { Reçu: 'var(--chart-1)', Envoyé: 'var(--chart-1)', 'En cours': 'var(--chart-3)', Répondu: 'var(--chart-4)', Archivé: 'var(--chart-5)' };

/**
 * Tableau de bord (React + shadcn/ui). Données : DashboardController.
 */
export default function Dashboard({ user, isAdmin, stats, personalTrends, trends, myTreatments, activity, overview, adminOverview, links }) {
    return (
        <>
            <Head title="Tableau de bord" />

            <Greeting
                name={user.firstName}
                subtitle={`${user.department ? `${user.department} · ` : ''}${user.role} — voici un aperçu de vos dossiers.`}
            />

            <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Chiffres clés">
                {stats.map((stat) => (
                    <StatCard key={stat.key} stat={stat} tone={stat.key === 'overdue' ? 'rose' : 'cyan'} />
                ))}
            </section>

            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                {isAdmin ? (
                    <TrendChart
                        className="xl:col-span-2"
                        trends={trends}
                        useSeriesColors
                        title="Évolution des dossiers"
                        description="Dossiers émis ou reçus par chaque département"
                        testId="trend-chart"
                    />
                ) : (
                    <TrendChart
                        className="xl:col-span-2"
                        trends={personalTrends}
                        title="Mon activité"
                        description="Dossiers que vous avez reçus et envoyés"
                        testId="personal-chart"
                    />
                )}
                <ActivityList items={activity.items} url={activity.url} />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                <BarChartCard
                    className="xl:col-span-2"
                    title={isAdmin ? 'Dossiers par statut' : 'Mes traitements'}
                    description={isAdmin ? 'Tous les dossiers de l’organisation' : 'Dossiers reçus, par état de traitement'}
                    rows={myTreatments.map((row) => ({ ...row, color: statusColors[row.label] }))}
                />
                <OverviewCard items={overview} />
            </div>

            {trends && !isAdmin && (
                <TrendChart
                    className="mt-6"
                    trends={trends}
                    useSeriesColors
                    title="Évolution des dossiers"
                    description={trends.periods.month.series.length === 1 ? 'Dossiers émis ou reçus par le département' : 'Dossiers émis ou reçus par chaque département'}
                    testId="trend-chart"
                />
            )}

            {adminOverview && <AdminOverview overview={adminOverview} statisticsUrl={links.statistics} />}
        </>
    );
}

function OverviewCard({ items }) {
    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle>Vue d’ensemble</CardTitle>
            </CardHeader>
            <CardContent>
                <ul className="space-y-1">
                    {items.map((item) => (
                        <li key={item.label}>
                            <a href={item.url ?? undefined} className={cn('flex items-center gap-3 rounded-lg px-2 py-2.5 transition', item.url && 'hover:bg-muted/60')}>
                                <span className="grid size-9 place-items-center rounded-lg border bg-accent text-accent-foreground">
                                    <AppIcon name={item.icon} className="size-4" />
                                </span>
                                <span className="flex-1 text-sm font-medium">{item.label}</span>
                                <span className="text-sm font-bold tabular-nums">{item.value}</span>
                            </a>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

function AdminOverview({ overview, statisticsUrl }) {
    const { departments, totals, attention, links } = overview;

    return (
        <section className="mt-10" aria-labelledby="admin-overview">
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="admin-overview" className="text-lg font-bold">Vue d’ensemble des départements</h2>
                    <p className="text-sm text-muted-foreground">
                        {departments.length} département(s) actif(s) · {totals.users} utilisateur(s) actif(s)
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button asChild variant="outline" size="sm">
                        <a href={links.users}><Users /> Utilisateurs</a>
                    </Button>
                    {statisticsUrl && (
                        <Button asChild size="sm">
                            <a href={statisticsUrl}><BarChart3 /> Statistiques</a>
                        </Button>
                    )}
                </div>
            </div>

            <div className="grid items-start gap-6 xl:grid-cols-3">
                <Card className="xl:col-span-2">
                    <CardHeader>
                        <CardTitle>Départements</CardTitle>
                        <CardDescription>Charge de traitement par service</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y">
                            {departments.length === 0 && <li className="py-8 text-center text-sm text-muted-foreground">Aucun département actif.</li>}
                            {departments.map((department) => {
                                const state =
                                    department.overdue > 2 ? ['Urgent', 'destructive'] : department.overdue > 0 ? ['En retard', 'warning'] : department.open > 0 ? ['En cours', 'info'] : ['À jour', 'ok'];

                                return (
                                    <li key={department.id} className={cn('flex flex-wrap items-center gap-x-4 gap-y-2 py-3', department.overdue > 0 && '-mx-2 rounded-lg bg-destructive/5 px-2')}>
                                        <span className="grid size-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-cyan-400 to-violet-500 text-xs font-bold text-white">
                                            {department.name.slice(0, 2).toUpperCase()}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <a href={department.url} className="font-semibold hover:text-primary">{department.name}</a>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {department.activeUsers} actif(s) / {department.users} membre(s)
                                                {department.manager && ` · ${department.manager}`}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2 text-sm">
                                            {department.overdue > 0 && <span className="font-semibold text-destructive">{department.overdue} en retard</span>}
                                            {department.open > 0 && <Badge variant="secondary">{department.open} en cours</Badge>}
                                            <StateBadge label={state[0]} tone={state[1]} />
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </CardContent>
                </Card>

                <div className="space-y-6">
                    <Card className="border-primary/30 bg-gradient-to-br from-card via-accent/40 to-card">
                        <CardHeader>
                            <Badge variant="outline" className="w-fit border-primary/30 text-primary"><Sparkles /> Point d’attention</Badge>
                            <CardTitle className="mt-2">
                                {attention ? `${attention.name} a ${attention.overdue} traitement(s) en retard` : 'Aucun retard dans l’organisation'}
                            </CardTitle>
                            <CardDescription>
                                {attention ? `Sur ${attention.open} dossier(s) en cours. Ce département mérite une attention prioritaire.` : 'Tous les traitements ouverts sont dans les délais.'}
                            </CardDescription>
                        </CardHeader>
                    </Card>

                    <Card className="gap-3">
                        <CardHeader>
                            <CardTitle>Suivi global</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <div>
                                <div className="mb-1.5 flex justify-between">
                                    <span className="text-muted-foreground">Taux de réponse</span>
                                    <span className="font-bold tabular-nums">{totals.answerRate !== null ? `${totals.answerRate} %` : '—'}</span>
                                </div>
                                <Progress value={totals.answerRate ?? 0} aria-label="Taux de réponse" />
                            </div>
                            <Row label="Traitements ouverts" value={totals.open} />
                            <Row label="Délais dépassés" value={totals.overdue} danger={totals.overdue > 0} />
                            <Row label="Départements actifs" value={departments.length} />
                        </CardContent>
                    </Card>

                    <Card className="gap-2">
                        <CardHeader>
                            <CardTitle>Actions rapides</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            <QuickAction href={links.createUser} icon={UserPlus} label="Créer un utilisateur" />
                            <QuickAction href={links.departments} icon={Users} label="Gérer les départements" />
                            <QuickAction href={links.settings} icon={Settings} label="Paramètres" />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>
    );
}

function StateBadge({ label, tone }) {
    const styles = {
        destructive: 'bg-destructive/10 text-destructive',
        warning: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        info: 'bg-primary/10 text-primary',
        ok: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    };

    return <span className={cn('rounded-full px-2.5 py-1 text-xs font-semibold', styles[tone])}>{label}</span>;
}

function Row({ label, value, danger = false }) {
    return (
        <div className="flex justify-between border-t pt-3">
            <span className="text-muted-foreground">{label}</span>
            <span className={cn('font-bold tabular-nums', danger && 'text-destructive')}>{value}</span>
        </div>
    );
}

function QuickAction({ href, icon: Icon, label }) {
    return (
        <a href={href} className="flex items-center gap-3 rounded-lg px-2 py-2 text-sm hover:bg-muted/60">
            <span className="grid size-8 place-items-center rounded-lg bg-accent text-accent-foreground"><Icon className="size-4" /></span>
            <span className="flex-1 font-medium">{label}</span>
            <ChevronRight className="size-4 text-muted-foreground" />
        </a>
    );
}
