import { toast } from 'sonner';

/**
 * Notifications en temps réel (WebSocket, Laravel Reverb).
 *
 * L'utilisateur écoute son canal privé « App.Models.User.{id} » : à chaque nouvelle
 * notification, la cloche (Alpine, « notificationBell ») et la page « Notifications »
 * se mettent à jour, et un toast s'affiche. Si le serveur temps réel est arrêté,
 * rien ne casse : les notifications restent visibles au prochain chargement de page.
 */
export function listenToNotifications(userId) {
    if (!userId || !import.meta.env.VITE_REVERB_APP_KEY) {
        return;
    }

    // Chargé après l'affichage de la page, pour ne pas la ralentir.
    Promise.all([import('laravel-echo'), import('pusher-js')])
        .then(([{ default: Echo }, { default: Pusher }]) => {
            window.Pusher = Pusher;

            const secure = window.location.protocol === 'https:';
            const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);

            window.Echo = new Echo({
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY,
                // Même machine que l'application : fonctionne aussi depuis un téléphone du réseau local.
                wsHost: window.location.hostname,
                wsPort: port,
                wssPort: port,
                forceTLS: secure,
                enabledTransports: ['ws', 'wss'],
            });

            window.Echo.private(`App.Models.User.${userId}`).notification((payload) => {
                const notification = {
                    id: payload.id,
                    message: payload.message,
                    reference: payload.reference,
                    url: `/notifications/${payload.id}`,
                    createdAt: payload.created_at,
                };

                window.dispatchEvent(new CustomEvent('notification-received', { detail: notification }));

                toast(notification.message, {
                    description: notification.reference ?? undefined,
                    action: { label: 'Ouvrir', onClick: () => { window.location.href = notification.url; } },
                });
            });
        })
        .catch(() => {
            // Connexion temps réel indisponible : l'application continue sans.
        });
}
