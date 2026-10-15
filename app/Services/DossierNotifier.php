<?php

namespace App\Services;

use App\Models\Dossier;
use App\Models\User;
use App\Notifications\DossierNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Envoie les notifications internes d'un dossier, uniquement après la
 * validation de la transaction en cours (jamais pour une action annulée),
 * sans notifier l'auteur de l'action ni un compte désactivé.
 */
class DossierNotifier
{
    /**
     * @param  User|iterable<User|null>|null  $users
     */
    public function notify(User|iterable|null $users, string $type, string $message, Dossier $dossier): void
    {
        $actorId = Auth::id();

        $recipients = Collection::wrap($users instanceof User ? [$users] : $users)
            ->filter()
            ->unique('id')
            ->reject(fn (User $user) => $user->id === $actorId || ! $user->is_active)
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        DB::afterCommit(fn () => Notification::send($recipients, new DossierNotification($type, $message, $dossier)));
    }
}
