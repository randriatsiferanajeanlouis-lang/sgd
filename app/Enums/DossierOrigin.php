<?php

namespace App\Enums;

/**
 * Origine d'un dossier : demande interne ou demande externe.
 */
enum DossierOrigin: string
{
    case Internal = 'internal';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Interne',
            self::External => 'Externe',
        };
    }
}
