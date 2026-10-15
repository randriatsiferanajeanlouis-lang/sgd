<?php

namespace App\Enums;

/**
 * Nature d'une transmission de dossier.
 */
enum TransmissionType: string
{
    case Initial = 'initial';
    case Forward = 'forward';
    case Validation = 'validation';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Envoi',
            self::Forward => 'Réacheminement',
            self::Validation => 'Transmission au responsable',
            self::Return => 'Retour',
        };
    }
}
