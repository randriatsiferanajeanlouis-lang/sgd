<?php

namespace App\Enums;

/**
 * Statut propre à chaque destinataire d'un dossier, indépendant des autres.
 */
enum RecipientStatus: string
{
    case Received = 'received';
    case InProgress = 'in_progress';
    case Answered = 'answered';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Reçu',
            self::InProgress => 'En cours',
            self::Answered => 'Répondu',
            self::Archived => 'Archivé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Received => 'blue',
            self::InProgress => 'amber',
            self::Answered => 'green',
            self::Archived => 'slate',
        };
    }

    /**
     * Traitement encore ouvert : échéances, relances et alertes s'appliquent.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Received, self::InProgress], true);
    }
}
