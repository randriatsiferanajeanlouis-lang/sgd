<?php

namespace App\Enums;

/**
 * État d'une demande de validation au responsable (sous-étape du traitement,
 * le statut principal du destinataire reste « En cours »).
 */
enum ValidationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À valider',
            self::Approved => 'Validé',
            self::Rejected => 'Refusé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }
}
