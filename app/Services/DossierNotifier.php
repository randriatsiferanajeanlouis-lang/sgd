<?php

namespace App\Services;

use App\Enums\ResponseDecision;
use App\Models\Dossier;
use App\Models\User;
use App\Notifications\DossierNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

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
    public function notify(User|iterable|null $users, string $type, string $message, Dossier $dossier, ?ResponseDecision $decision = null): void
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

        // Un destinataire à la fois : si le serveur temps réel (Reverb) est arrêté, la notification
        // reste enregistrée en base et l'action de l'utilisateur n'est jamais interrompue.
        DB::afterCommit(function () use ($recipients, $type, $message, $dossier, $decision) {
            foreach ($recipients as $recipient) {
                try {
                    Notification::send($recipient, new DossierNotification($type, $message, $dossier, $decision));
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        });
    }
}
