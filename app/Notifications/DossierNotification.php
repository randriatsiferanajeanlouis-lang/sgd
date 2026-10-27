<?php

namespace App\Notifications;

use App\Enums\ResponseDecision;
use App\Models\Dossier;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
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
        public readonly ?ResponseDecision $decision = null,
    ) {}

    /**
     * Message à afficher : une décision positive est précédée de ✅, une
     * décision négative de ❌. Les notifications enregistrées avant l'ajout
     * du champ « decision » sont reconnues d'après leur texte.
     *
     * @param  array<string, mixed>  $data
     */
    public static function displayMessage(array $data): string
    {
        $message = (string) ($data['message'] ?? 'Notification');
        $decision = $data['decision'] ?? match (true) {
            str_contains($message, 'a rendu une décision Positive') => ResponseDecision::Positive->value,
            str_contains($message, 'a rendu une décision Négative') => ResponseDecision::Negative->value,
            default => null,
        };

        return match ($decision) {
            ResponseDecision::Positive->value => '✅ '.$message,
            ResponseDecision::Negative->value => '❌ '.$message,
            default => $message,
        };
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Diffusion sur le canal privé de l'utilisateur : la cloche et la page
     * « Notifications » se mettent à jour sans rechargement. Envoi immédiat
     * (connexion « sync »), sans file d'attente ni worker. Le message porte
     * déjà ✅ / ❌ pour une décision.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data = $this->toArray($notifiable);

        return (new BroadcastMessage([
            'message' => self::displayMessage($data),
            'reference' => $data['reference'],
            'kind' => $this->type,
            'decision' => $this->decision?->value,
            'created_at' => now()->toIso8601String(),
        ]))->onConnection('sync');
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
            'decision' => $this->decision?->value,
        ];
    }
}
