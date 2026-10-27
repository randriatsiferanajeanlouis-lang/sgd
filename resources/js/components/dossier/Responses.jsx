import { Send } from 'lucide-react';
import SectionTitle from '@/components/app/SectionTitle';
import StatusBadge from '@/components/app/StatusBadge';
import UserAvatar from '@/components/app/UserAvatar';
import DocumentLink from '@/components/dossier/DocumentLink';
import { cn } from '@/lib/utils';

/** Réponses visibles par l'utilisateur (déjà filtrées par Laravel). */
export default function Responses({ responses }) {
    if (responses.length === 0) {
        return null;
    }

    return (
        <section className="card" aria-labelledby="responses-title">
            <SectionTitle id="responses-title" Icon={Send} count={responses.length}>
                Réponses
            </SectionTitle>

            <ol className="mt-5 space-y-4">
                {responses.map((response) => (
                    <li key={response.id} className={cn('overflow-hidden rounded-lg border', response.isFinal ? 'border-green-300 dark:border-green-800' : 'border-univ-line dark:border-slate-800')}>
                        <div className={cn('flex flex-wrap items-center justify-between gap-3 px-4 py-3', response.isFinal ? 'bg-green-50 dark:bg-green-950/30' : 'bg-univ-surface/60 dark:bg-slate-950/30')}>
                            <div className="flex min-w-0 items-center gap-3">
                                <UserAvatar photoUrl={response.author.photoUrl} initials={response.author.initials} size="size-9" className="shrink-0 text-xs" />
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        <span className="font-semibold">{response.author.name}</span>
                                        {response.isFinal && <StatusBadge color="green">Réponse officielle</StatusBadge>}
                                        {response.decision && <StatusBadge color={response.decision.color}>{response.decision.label}</StatusBadge>}
                                    </div>
                                    <div className="text-xs text-slate-500 dark:text-slate-400">{response.author.detail}</div>
                                </div>
                            </div>
                            <time className="text-xs text-slate-500 dark:text-slate-400" dateTime={response.iso}>
                                {response.date} à {response.time}
                            </time>
                        </div>

                        <div className="px-4 py-4">
                            {response.content && <div className="leading-relaxed whitespace-pre-line">{response.content}</div>}

                            {(response.justification || response.recommendation) && (
                                <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                    {response.justification && (
                                        <div className="rounded-md bg-univ-surface/60 px-3 py-2 dark:bg-slate-950/30">
                                            <dt className="text-xs font-bold text-slate-500 dark:text-slate-400">Justification :</dt>
                                            <dd className="mt-0.5">{response.justification}</dd>
                                        </div>
                                    )}
                                    {response.recommendation && (
                                        <div className="rounded-md bg-univ-surface/60 px-3 py-2 dark:bg-slate-950/30">
                                            <dt className="text-xs font-bold text-slate-500 dark:text-slate-400">Recommandation :</dt>
                                            <dd className="mt-0.5">{response.recommendation}</dd>
                                        </div>
                                    )}
                                </dl>
                            )}

                            {response.sent && <div className="mt-3 text-sm text-slate-500 dark:text-slate-400">Envoyée le {response.sent}</div>}

                            {response.documents.length > 0 && (
                                <ul className="mt-4 flex flex-wrap gap-2 border-t border-univ-line pt-3 text-sm dark:border-slate-800">
                                    {response.documents.map((document) => (
                                        <li key={document.id} className="rounded-md border border-univ-line px-2.5 py-1 dark:border-slate-700">
                                            <DocumentLink document={document} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </li>
                ))}
            </ol>
        </section>
    );
}
