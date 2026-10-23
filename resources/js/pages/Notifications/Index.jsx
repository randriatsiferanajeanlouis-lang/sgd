import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import PageHeader from '@/components/app/PageHeader';
import Pagination from '@/components/app/Pagination';
import StatusBadge from '@/components/app/StatusBadge';
import { cn } from '@/lib/utils';

/**
 * Notifications (React) : les notifications de l'utilisateur connecté.
 * Un clic passe par notifications.open (marquée lue, puis ouverture de
 * l'élément concerné) : vrai lien, la page d'arrivée peut être Blade ou React.
 */
export default function Index({ notifications, hasUnread, urls }) {
    const [marking, setMarking] = useState(false);
    const items = notifications.data ?? [];

    const markAll = () => {
        setMarking(true);
        router.post(urls.readAll, {}, { onFinish: () => setMarking(false) });
    };

    return (
        <>
            <Head title="Notifications" />

            <PageHeader
                title="Notifications"
                actions={
                    hasUnread && (
                        <button type="button" className="btn-secondary" disabled={marking} onClick={markAll}>
                            Tout marquer comme lu
                        </button>
                    )
                }
            />

            <div className="card divide-y divide-slate-100 p-0 dark:divide-slate-800">
                {items.map((notification) => (
                    <a
                        key={notification.id}
                        href={notification.url}
                        className={cn('flex items-start gap-4 px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/60', notification.unread && 'bg-brand-50/60 dark:bg-brand-900/20')}
                    >
                        <span className={cn('mt-2 size-2 shrink-0 rounded-full', notification.unread ? 'bg-brand-600' : 'bg-transparent')} />
                        <div className="flex-1">
                            <div className="text-sm font-medium">{notification.message}</div>
                            <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {notification.reference && `${notification.reference} · `}
                                {notification.type} · {notification.date}
                            </div>
                        </div>
                        {notification.unread && <StatusBadge color="blue">Non lu</StatusBadge>}
                    </a>
                ))}
                {items.length === 0 && <p className="px-5 py-10 text-center text-slate-500">Aucune notification.</p>}
            </div>

            <div className="mt-4">
                <Pagination paginator={notifications} />
            </div>
        </>
    );
}
