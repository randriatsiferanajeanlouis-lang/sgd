import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Bar, BarChart, XAxis } from 'recharts';
import { Activity, CalendarDays, Eye, LogIn, RotateCcw, Search, ShieldAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';

const ALL = 'all';

const badgeColors = {
    sky: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
    violet: 'bg-violet-500/10 text-violet-700 dark:text-violet-300',
    amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
    rose: 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    slate: 'bg-slate-500/10 text-slate-700 dark:text-slate-300',
    emerald: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    cyan: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300',
};

/**
 * Journal d'activité (lecture seule) : connexions et actions d'administration.
 */
export default function ActivityLog({ logs, filters, categories, actors, summary, indexUrl }) {
    const [form, setForm] = useState(filters);
    const [selected, setSelected] = useState(null);

    const apply = (next = form) => {
        const query = Object.fromEntries(Object.entries(next).filter(([, value]) => value !== '' && value !== ALL));
        router.get(indexUrl, query, { preserveState: true, preserveScroll: true, replace: true });
    };

    const update = (key, value) => setForm((current) => ({ ...current, [key]: value === ALL ? '' : value }));
    const reset = () => {
        const empty = { category: '', actor: '', search: '', from: '', to: '' };
        setForm(empty);
        apply(empty);
    };

    return (
        <>
            <Head title="Journal d’activité" />

            <div className="mb-6">
                <p className="kicker mb-1">Supervision</p>
                <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Journal d’activité</h1>
                <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                    Connexions et actions d’administration (comptes, départements, rôles, paramètres, sauvegardes). Le journal est en ajout seul : aucune ligne ne peut être modifiée ni supprimée.
                </p>
            </div>

            <section className="grid gap-4 lg:grid-cols-4" aria-label="Synthèse">
                <Kpi icon={Activity} label="Actions aujourd’hui" value={summary.today} />
                <Kpi icon={LogIn} label="Connexions aujourd’hui" value={summary.logins} />
                <Kpi icon={ShieldAlert} label="Échecs de connexion (7 j)" value={summary.failed} danger={summary.failed > 0} />
                <Card className="gap-2 py-4">
                    <CardHeader className="px-5">
                        <CardDescription>14 derniers jours · {summary.week} action(s) sur 7 j</CardDescription>
                    </CardHeader>
                    <CardContent className="px-5">
                        <ChartContainer config={{ actions: { label: 'Actions', color: 'var(--chart-1)' } }} className="aspect-auto h-16 w-full">
                            <BarChart data={summary.daily} margin={{ top: 0, right: 0, bottom: 0, left: 0 }}>
                                <XAxis dataKey="day" hide />
                                <ChartTooltip cursor={false} content={<ChartTooltipContent hideIndicator />} />
                                <Bar dataKey="actions" fill="var(--color-actions)" radius={3} isAnimationActive={false} />
                            </BarChart>
                        </ChartContainer>
                    </CardContent>
                </Card>
            </section>

            <Card className="mt-6 gap-0 pb-0">
                <CardHeader className="border-b pb-5">
                    <form
                        className="grid gap-3 md:grid-cols-2 xl:grid-cols-[1.4fr_1fr_1fr_auto_auto_auto]"
                        onSubmit={(event) => {
                            event.preventDefault();
                            apply();
                        }}
                    >
                        <div className="grid gap-1.5">
                            <Label htmlFor="log-search">Rechercher</Label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input id="log-search" className="pl-8" placeholder="Action, utilisateur, commentaire, IP…" value={form.search} onChange={(event) => update('search', event.target.value)} />
                            </div>
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Catégorie</Label>
                            <Select value={form.category || ALL} onValueChange={(value) => update('category', value)}>
                                <SelectTrigger className="w-full" aria-label="Catégorie"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Toutes</SelectItem>
                                    {categories.map((category) => <SelectItem key={category} value={category}>{category}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Utilisateur</Label>
                            <Select value={form.actor || ALL} onValueChange={(value) => update('actor', value)}>
                                <SelectTrigger className="w-full" aria-label="Utilisateur"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Tous</SelectItem>
                                    {actors.map((actor) => <SelectItem key={actor.id} value={actor.id}>{actor.name}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="log-from">Du</Label>
                            <Input id="log-from" type="date" value={form.from} onChange={(event) => update('from', event.target.value)} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="log-to">Au</Label>
                            <Input id="log-to" type="date" value={form.to} onChange={(event) => update('to', event.target.value)} />
                        </div>
                        <div className="flex items-end gap-2">
                            <Button type="submit">Filtrer</Button>
                            <Button type="button" variant="ghost" size="icon" onClick={reset} aria-label="Réinitialiser les filtres"><RotateCcw /></Button>
                        </div>
                    </form>
                </CardHeader>
                <CardContent className="px-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-6">Date</TableHead>
                                <TableHead>Utilisateur</TableHead>
                                <TableHead>Action</TableHead>
                                <TableHead>Cible</TableHead>
                                <TableHead className="hidden lg:table-cell">Adresse IP</TableHead>
                                <TableHead className="pr-6 text-right"><span className="sr-only">Détail</span></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {logs.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="py-12 text-center text-muted-foreground">Aucune action ne correspond aux filtres.</TableCell>
                                </TableRow>
                            )}
                            {logs.data.map((log) => (
                                <TableRow key={log.id}>
                                    <TableCell className="pl-6 whitespace-nowrap">
                                        <div className="font-medium tabular-nums">{log.date}</div>
                                        <div className="text-xs text-muted-foreground">{log.time} · {log.relative}</div>
                                    </TableCell>
                                    <TableCell>
                                        {log.actor ? (
                                            <div className="flex items-center gap-2">
                                                <span className="grid size-7 place-items-center rounded-full bg-accent text-[10px] font-bold text-accent-foreground">{log.actor.initials}</span>
                                                <span className="font-medium">{log.actor.name}</span>
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground">Système</span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="font-medium">{log.label}</div>
                                        <span className={cn('mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold', badgeColors[log.color] ?? badgeColors.slate)}>{log.category}</span>
                                    </TableCell>
                                    <TableCell className="max-w-56 truncate">{log.target ?? '—'}</TableCell>
                                    <TableCell className="hidden font-mono text-xs text-muted-foreground lg:table-cell">{log.ip ?? '—'}</TableCell>
                                    <TableCell className="pr-6 text-right">
                                        <Button variant="ghost" size="sm" onClick={() => setSelected(log)}><Eye /> Détail</Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            {logs.last_page > 1 && (
                <nav className="mt-4 flex flex-wrap items-center gap-1" aria-label="Pagination">
                    <span className="mr-2 text-sm text-muted-foreground">{logs.total} action(s)</span>
                    {logs.links.map((link, index) =>
                        link.url ? (
                            <Button key={index} asChild size="sm" variant={link.active ? 'default' : 'outline'}>
                                <Link href={link.url} preserveScroll preserveState dangerouslySetInnerHTML={{ __html: link.label }} />
                            </Button>
                        ) : (
                            <Button key={index} size="sm" variant="outline" disabled dangerouslySetInnerHTML={{ __html: link.label }} />
                        ),
                    )}
                </nav>
            )}

            <Dialog open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}>
                <DialogContent className="sm:max-w-lg">
                    {selected && (
                        <>
                            <DialogHeader>
                                <DialogTitle>{selected.label}</DialogTitle>
                                <DialogDescription className="flex items-center gap-1.5">
                                    <CalendarDays className="size-3.5" /> {selected.date} à {selected.time} · {selected.actor?.name ?? 'Système'}
                                </DialogDescription>
                            </DialogHeader>
                            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                                <dt className="text-muted-foreground">Catégorie</dt><dd><Badge variant="secondary">{selected.category}</Badge></dd>
                                <dt className="text-muted-foreground">Cible</dt><dd>{selected.target ?? '—'}</dd>
                                <dt className="text-muted-foreground">Département</dt><dd>{selected.department ?? '—'}</dd>
                                <dt className="text-muted-foreground">Adresse IP</dt><dd className="font-mono text-xs">{selected.ip ?? '—'}</dd>
                                {selected.comment && (<><dt className="text-muted-foreground">Commentaire</dt><dd>{selected.comment}</dd></>)}
                            </dl>
                            {selected.changes.length > 0 && (
                                <div className="mt-2 overflow-hidden rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow><TableHead>Champ</TableHead><TableHead>Avant</TableHead><TableHead>Après</TableHead></TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {selected.changes.map((change) => (
                                                <TableRow key={change.field}>
                                                    <TableCell className="font-medium">{change.field}</TableCell>
                                                    <TableCell className="text-muted-foreground line-through decoration-rose-500/50 whitespace-normal">{change.old ?? '—'}</TableCell>
                                                    <TableCell className="whitespace-normal text-emerald-700 dark:text-emerald-300">{change.new ?? '—'}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function Kpi({ icon: Icon, label, value, danger = false }) {
    return (
        <Card className="py-5">
            <CardContent className="flex items-center gap-4 px-5">
                <span className={cn('grid size-11 place-items-center rounded-xl bg-gradient-to-br text-white shadow-lg', danger ? 'from-rose-400 to-rose-600 shadow-rose-500/30' : 'from-cyan-400 to-cyan-600 shadow-cyan-500/30')}>
                    <Icon className="size-5" aria-hidden="true" />
                </span>
                <div>
                    <CardTitle className="text-sm">{label}</CardTitle>
                    <p className={cn('text-2xl font-extrabold tabular-nums', danger && 'text-destructive')}>{value}</p>
                </div>
            </CardContent>
        </Card>
    );
}
