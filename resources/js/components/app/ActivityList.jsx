import { Activity, FileText, LogIn, Settings, ShieldCheck, Users } from 'lucide-react';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { shortText } from '@/lib/text';

const icons = { dossier: FileText, Connexions: LogIn, Comptes: Users, 'Rôles et permissions': ShieldCheck, Paramétrage: Settings };

/** Encadré « Activité récente » du tableau de bord. */
export default function ActivityList({ items, url }) {
    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <span className="size-2 rounded-full bg-primary shadow-[0_0_10px] shadow-primary" aria-hidden="true" />
                    Activité récente
                </CardTitle>
                {url && (
                    <CardAction>
                        <a href={url} className="text-xs font-semibold text-primary hover:underline">
                            Voir tout
                        </a>
                    </CardAction>
                )}
            </CardHeader>
            <CardContent>
                {items.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">Aucune activité récente.</p>
                ) : (
                    <ul className="divide-y">
                        {items.map((item) => {
                            const Icon = icons[item.kind] ?? Activity;

                            return (
                                <li key={item.id}>
                                    <a href={item.url} className="flex gap-3 rounded-lg py-3 transition hover:bg-muted/50">
                                        <span className="grid size-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-cyan-400 to-cyan-600 text-white shadow-md shadow-cyan-500/25">
                                            <Icon className="size-4" aria-hidden="true" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-semibold">{item.title}</span>
                                            {item.reference ? (
                                                <span className="block text-xs text-muted-foreground" title={item.subject}>
                                                    <span className="font-mono whitespace-nowrap">{item.reference}</span> — {shortText(item.subject)}
                                                </span>
                                            ) : (
                                                <span className="block truncate text-xs text-muted-foreground">{item.subtitle}</span>
                                            )}
                                            <span className="block text-[11px] text-muted-foreground">
                                                {item.actor} · {item.relative}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
