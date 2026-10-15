<?php

namespace App\Services;

use App\Enums\RecipientStatus;
use App\Enums\TransmissionType;
use App\Models\Dossier;
use App\Models\DossierRecipient;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Envoi d'un dossier à des utilisateurs (département → utilisateur) :
 * une ligne de traitement par destinataire, une transmission, l'historique
 * et une notification. Utilisé par les demandes internes et externes.
 */
class DossierDispatcher
{
    public function __construct(
        private readonly DossierHistory $history,
        private readonly DossierNotifier $notifier,
    ) {}

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, DossierRecipient>
     */
    public function dispatch(Dossier $dossier, Collection $users, User $sender, ?CarbonInterface $dueAt, ?string $comment = null): Collection
    {
        $recipients = $users->map(function (User $user) use ($dossier, $sender, $dueAt, $comment) {
            $recipient = $dossier->recipients()->create([
                'department_id' => $user->department_id,
                'assigned_user_id' => $user->id,
                'status' => RecipientStatus::Received,
                'due_at' => $dueAt,
            ]);

            $dossier->transmissions()->create([
                'dossier_recipient_id' => $recipient->id,
                'from_department_id' => $sender->department_id,
                'to_department_id' => $user->department_id,
                'transmitted_by' => $sender->id,
                'type' => TransmissionType::Initial,
                'comment' => $comment,
                'transmitted_at' => now(),
            ]);

            $this->history->record($dossier, 'recipient.received', $recipient, newValues: [
                'destinataire' => $user->name,
                'département' => $user->department?->name,
                'statut' => RecipientStatus::Received->label(),
            ]);

            return $recipient;
        });

        $this->notifier->notify(
            $users,
            'dossier_received',
            "Nouveau dossier {$dossier->reference} : {$dossier->subject}",
            $dossier,
        );

        return $recipients;
    }
}
