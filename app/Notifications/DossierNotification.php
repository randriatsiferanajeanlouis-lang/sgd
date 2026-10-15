<?php

namespace App\Notifications;

use App\Models\Dossier;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notification interne liée à un dossier : type, message, dossier et lien.
 * Le type est l'une des clés de lang/fr/notifications.php.
 */
class DossierNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly Dossier $dossier,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'dossier_id' => $this->dossier->id,
            'reference' => $this->dossier->reference,
            'url' => route('dossiers.show', $this->dossier, false),
        ];
    }
}
