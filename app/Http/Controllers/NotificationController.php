<?php

namespace App\Http\Controllers;

use App\Notifications\DossierNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        return Inertia::render('Notifications/Index', [
            'title' => 'Notifications',
            'hasUnread' => $request->user()->unreadNotifications()->exists(),
            'notifications' => $request->user()->notifications()->paginate(20)
                ->through(fn (DatabaseNotification $notification) => [
                    'id' => $notification->id,
                    'url' => route('notifications.open', $notification->id),
                    'unread' => $notification->unread(),
                    'message' => DossierNotification::displayMessage($notification->data),
                    'reference' => $notification->data['reference'] ?? null,
                    'type' => __('notifications.types.'.($notification->data['type'] ?? 'other')),
                    'date' => $notification->created_at->format('d/m/Y H:i'),
                ]),
            'urls' => ['readAll' => route('notifications.read-all')],
        ]);
    }

    /**
     * Ouvre l'élément concerné (dossier, compte…) et marque la notification comme lue.
     * Seules les notifications de l'utilisateur connecté sont accessibles.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        // Chemin interne uniquement : « //hôte » ou « /\hôte » mèneraient vers un autre site.
        $isInternalPath = is_string($url) && preg_match('#^/(?![/\\\\])[^\s]*$#', $url) === 1;

        return $isInternalPath ? redirect($url) : redirect()->route('notifications.index');
    }

    public function markAllAsRead(Request $request): Response
    {
        $request->user()->unreadNotifications->markAsRead();
        $request->session()->flash('status', 'Toutes les notifications ont été marquées comme lues.');

        // Rechargement complet : le compteur de la cloche (en-tête Blade) est remis à zéro.
        return Inertia::location(route('notifications.index'));
    }
}
