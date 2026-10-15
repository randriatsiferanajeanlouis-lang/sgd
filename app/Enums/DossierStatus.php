<?php

namespace App\Enums;

/**
 * Statut global d'un dossier, vu par l'expéditeur. Pas de statut « En
 * attente » ni « Retard » : un délai dépassé est une alerte, pas un statut.
 */
enum DossierStatus: string
{
    case Sent = 'sent';
    case InProgress = 'in_progress';
    case Answered = 'answered';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Envoyé',
            self::InProgress => 'En cours',
            self::Answered => 'Répondu',
            self::Archived => 'Archivé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sent => 'blue',
            self::InProgress => 'amber',
            self::Answered => 'green',
            self::Archived => 'slate',
        };
    }
}
