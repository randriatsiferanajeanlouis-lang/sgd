<?php

namespace App\Enums;

/**
 * Catégorie d'un document rattaché à un dossier.
 */
enum DocumentCategory: string
{
    case Incoming = 'incoming';
    case Attachment = 'attachment';
    case Response = 'response';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Incoming => 'Courrier reçu',
            self::Attachment => 'Pièce jointe',
            self::Response => 'Réponse',
            self::Internal => 'Document interne',
        };
    }
}
