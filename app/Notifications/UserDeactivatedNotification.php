<?php

namespace App\Notifications;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Avertit l'administration qu'un responsable a désactivé un employé.
 */
class UserDeactivatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $deactivatedUser,
        private readonly User $manager,
        private readonly CarbonInterface $deactivatedAt,
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
            'type' => 'user_deactivated',
            'message' => "{$this->manager->name} a désactivé le compte de {$this->deactivatedUser->name}.",
            'department' => $this->deactivatedUser->department?->name,
            'manager' => $this->manager->name,
            'user' => $this->deactivatedUser->name,
            'user_id' => $this->deactivatedUser->id,
            'date' => $this->deactivatedAt->format('d/m/Y'),
            'time' => $this->deactivatedAt->format('H:i'),
            'url' => route('admin.users.edit', $this->deactivatedUser, false),
        ];
    }
}
