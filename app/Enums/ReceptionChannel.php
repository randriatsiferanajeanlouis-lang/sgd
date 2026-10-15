<?php

namespace App\Enums;

/**
 * Canal par lequel une demande externe a été reçue.
 */
enum ReceptionChannel: string
{
    case Email = 'email';
    case Mail = 'mail';
    case Phone = 'phone';
    case InPerson = 'in_person';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Mail => 'Courrier',
            self::Phone => 'Téléphone',
            self::InPerson => 'Dépôt physique',
            self::Other => 'Autre',
        };
    }
}
