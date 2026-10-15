<?php

namespace App\Enums;

/**
 * Rôle du département destinataire : doit agir, ou est simplement informé.
 */
enum RecipientType: string
{
    case Action = 'action';
    case Information = 'information';

    public function label(): string
    {
        return match ($this) {
            self::Action => 'Pour action',
            self::Information => 'Pour information',
        };
    }
}
