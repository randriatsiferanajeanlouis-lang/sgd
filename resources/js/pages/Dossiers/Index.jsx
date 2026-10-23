import { Head, Link } from '@inertiajs/react';
import DossierFilters, { cleanParams } from '@/components/app/DossierFilters';
import DossierTable from '@/components/app/DossierTable';
import PageHeader from '@/components/app/PageHeader';
import { cn } from '@/lib/utils';

const BOXES = [
    ['received', 'Reçus'],
    ['sent', 'Envoyés'],
    ['archived', 'Archivés'],
    ['all', 'Tous'],
];

/**
 * Mes dossiers (React) : totaux par département, boîtes Reçus / Envoyés /
 * Archivés / Tous, recherche instantanée, filtre de statut et tableau.
 * Les droits restent côté Laravel (DossierController@index, relatedTo).
 */
export default function Index({ box, selectedDepartment, filters, statuses, grandTotal, departmentTotals, dossiers, urls }) {
    const current = departmentTotals.find((row) => row.id === selectedDepartment);
    const href = (params) => `${urls.index}?${new URLSearchParams(cleanParams(params))}`;

    return (
        <>
            <Head title="Mes dossiers" />

            <PageHeader title="Mes dossiers" subtitle="Les dossiers que vous avez reçus ou envoyés, selon vos droits d’accès." />

            {/* Synthèse : total général et total par département (un clic filtre la liste) */}
            <section className="mb-6" aria-label="Totaux des dossiers">
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
                    <Link
                        href={href({ box: 'all' })}
                        data-testid="total-all"
                        className={cn(
                            'col-span-2 flex flex-col justify-between gap-3 rounded-lg bg-univ-deep p-4 text-white transition hover:-translate-y-0.5 hover:shadow-[0_12px_26px_rgba(26,26,140,0.25)] sm:col-span-1',
                            box === 'all' && !selectedDepartment && 'ring-2 ring-univ-electric ring-offset-2 dark:ring-offset-slate-950',
                        )}
                    >
                        <span className="text-[11px] font-extrabold tracking-[0.1em] text-[#c9c9ff] uppercase">Tous mes dossiers</span>
                        <span className="text-3xl leading-none font-extrabold tabular-nums">{grandTotal}</span>
                        <span className="text-xs text-[#dedffd]">Total général</span>
                    </Link>

                    {departmentTotals.map((row) => (
                        <Link
                            key={row.id}
                            href={href({ box: 'all', department: row.id })}
                            data-testid="department-total"
                            title={`Voir mes dossiers concernant ${row.name}`}
                            className={cn(
                                'group flex flex-col justify-between gap-3 rounded-lg border border-t-2 border-univ-line border-t-brand-500 bg-white p-4 transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-[0_12px_26px_rgba(26,26,140,0.12)] dark:border-slate-800 dark:border-t-brand-300 dark:bg-slate-900',
                                row.id === selectedDepartment && 'bg-brand-50 ring-2 ring-univ-electric ring-offset-2 dark:bg-slate-800 dark:ring-offset-slate-950',
                            )}
                        >
                            <span className="truncate text-[11px] font-extrabold tracking-[0.08em] text-brand-500 uppercase dark:text-brand-300">{row.name}</span>
                            <span className="text-3xl leading-none font-extrabold text-univ-ink tabular-nums group-hover:text-brand-500 dark:text-white">{row.total}</span>
                            <span className="text-xs text-univ-muted dark:text-slate-400">dossier(s)</span>
                        </Link>
                    ))}
                </div>

                {selectedDepartment && (
                    <p className="mt-3 flex flex-wrap items-center gap-2 text-sm text-univ-muted dark:text-slate-400">
                        Liste filtrée : dossiers concernant <strong className="text-brand-500 dark:text-brand-300">{current?.name ?? '—'}</strong>
                        <Link href={href({ box })} className="font-bold text-brand-500 hover:underline dark:text-brand-300">
                            Retirer le filtre
                        </Link>
                    </p>
                )}
            </section>

            <nav className="mb-6 flex flex-wrap gap-2" aria-label="Boîtes">
                {BOXES.map(([key, label]) => (
                    <Link
                        key={key}
                        href={href({ box: key, department: selectedDepartment })}
                        className={box === key ? 'btn-primary' : 'btn-secondary'}
                        aria-current={box === key ? 'page' : undefined}
                    >
                        {label}
                    </Link>
                ))}
            </nav>

            <DossierFilters url={urls.index} filters={filters} statuses={statuses} keep={{ box, department: selectedDepartment }} />

            <DossierTable dossiers={dossiers} />
        </>
    );
}
