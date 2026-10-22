import { Calendar, Clock, Download } from 'lucide-react';
import SectionTitle from '@/components/app/SectionTitle';

/** Historique complet, regroupé par jour, avec exports PDF et Word. */
export default function History({ days, urls }) {
    const count = days.reduce((total, day) => total + day.rows.length, 0);

    return (
        <section id="historique" className="card scroll-mt-24" aria-labelledby="history-title">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <SectionTitle id="history-title" Icon={Clock} count={count}>
                    Historique complet
                </SectionTitle>
                <div className="flex gap-2">
                    <a href={urls.historyPdf} className="btn-secondary px-3 py-1.5">
                        <Download className="size-4" aria-hidden="true" /> Exporter PDF
                    </a>
                    <a href={urls.historyWord} className="btn-secondary px-3 py-1.5">
                        <Download className="size-4" aria-hidden="true" /> Exporter Word
                    </a>
                </div>
            </div>

            <div className="mt-6 space-y-6">
                {days.map((day) => (
                    <div key={day.date}>
                        <div className="mb-3 inline-flex items-center gap-2 rounded-md bg-univ-surface px-2.5 py-1 text-xs font-bold text-univ-muted dark:bg-slate-800 dark:text-slate-300">
                            <Calendar className="size-3.5" aria-hidden="true" /> {day.date}
                        </div>

                        <ol className="relative ml-3 border-l-2 border-univ-line dark:border-slate-700">
                            {day.rows.map((row, index) => (
                                <li key={index} className="relative pb-5 pl-6 last:pb-0">
                                    <span className="absolute top-1 -left-[7px] size-3 rounded-full border-2 border-white bg-brand-500 dark:border-slate-900 dark:bg-brand-400" aria-hidden="true" />
                                    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                        <span className="font-semibold text-univ-ink dark:text-white">{row.action}</span>
                                        <span className="text-xs text-slate-500 dark:text-slate-400">
                                            {row.time} · {row.user}
                                        </span>
                                    </div>
                                    {row.details && <p className="mt-1 text-xs leading-relaxed break-words text-slate-600 dark:text-slate-400">{row.details}</p>}
                                    {row.comment && (
                                        <p className="mt-2 rounded-md border-l-2 border-brand-300 bg-univ-surface/60 px-3 py-1.5 text-xs italic dark:border-brand-700 dark:bg-slate-950/30">{row.comment}</p>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </div>
                ))}
            </div>
        </section>
    );
}
