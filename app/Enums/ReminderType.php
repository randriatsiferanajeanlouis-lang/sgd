<?php

namespace App\Enums;

/**
 * Origine d'une relance.
 */
enum ReminderType: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatique',
            self::Manual => 'Manuelle',
        };
    }
}
