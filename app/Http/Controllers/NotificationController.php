<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
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

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Toutes les notifications ont été marquées comme lues.');
    }
}
