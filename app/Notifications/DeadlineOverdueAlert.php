<?php

namespace App\Notifications;

use App\Models\DossierRecipient;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerte par e-mail : le délai de réponse demandé est dépassé. Envoyée au
 * destinataire du traitement et à la personne qui a envoyé la demande.
 */
class DeadlineOverdueAlert extends Notification
{
    use Queueable;

    public function __construct(public readonly DossierRecipient $recipient) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $dossier = $this->recipient->dossier;
        $assignee = $this->recipient->assignedUser;
        $isAssignee = $notifiable->is($assignee);
        $sender = $dossier->isExternal()
            ? ($dossier->externalRequester?->full_name ?? 'Demandeur externe').' (enregistrée par '.$dossier->creator->name.')'
            : $dossier->creator->name;

        $mail = (new MailMessage)
            ->error()
            ->subject("Alerte : délai de réponse dépassé — {$dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line('**Une demande a dépassé le délai de réponse recommandé par le demandeur.**')
            ->line($isAssignee
                ? 'Vous n’avez pas encore répondu à cette demande, alors que l’échéance est passée.'
                : "{$assignee->name} n’a pas encore répondu à la demande que vous avez envoyée, alors que l’échéance est passée.")
            ->line("**Dossier :** {$dossier->reference} — {$dossier->subject}")
            ->line("**Expéditeur :** {$sender}")
            ->line("**Destinataire :** {$assignee->name}".($assignee->department ? " ({$assignee->department->name})" : ''));

        if ($dossier->urgencyLevel) {
            $mail->line("**Niveau d’urgence :** {$dossier->urgencyLevel->name}");
        }

        return $mail
            ->line('**Échéance dépassée depuis le :** '.$this->recipient->due_at->format('d/m/Y à H:i'))
            ->action('Ouvrir le dossier', route('dossiers.show', $dossier))
            ->line($isAssignee
                ? 'Merci de traiter cette demande au plus vite.'
                : 'Vous pouvez relancer le destinataire depuis le dossier.')
            ->salutation('— SOGEDIPROMA, suivi des dossiers');
    }
}
