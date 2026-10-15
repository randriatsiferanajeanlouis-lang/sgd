<?php

namespace App\Enums;

/**
 * Nature d'un demandeur externe.
 */
enum RequesterType: string
{
    case Person = 'person';
    case Organization = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::Person => 'Particulier',
            self::Organization => 'Organisation',
        };
    }
}
