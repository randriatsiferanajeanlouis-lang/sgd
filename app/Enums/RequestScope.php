<?php

namespace App\Enums;

/**
 * Portée d'un type de demande ou d'un niveau d'urgence.
 */
enum RequestScope: string
{
    case Internal = 'internal';
    case External = 'external';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Interne',
            self::External => 'Externe',
            self::Both => 'Interne et externe',
        };
    }
}
