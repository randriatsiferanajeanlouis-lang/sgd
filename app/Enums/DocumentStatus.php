<?php

namespace App\Enums;

/**
 * Cycle de vie d'une version de document. REPLACED et CANCELLED sortent du
 * fonctionnement opérationnel mais restent tracés.
 */
enum DocumentStatus: string
{
    case Active = 'active';
    case Replaced = 'replaced';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Replaced => 'Remplacée',
            self::Cancelled => 'Annulée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Replaced => 'slate',
            self::Cancelled => 'red',
        };
    }
}
