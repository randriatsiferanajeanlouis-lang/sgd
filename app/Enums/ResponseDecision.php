<?php

namespace App\Enums;

/**
 * Décision d'un responsable sur une demande externe.
 */
enum ResponseDecision: string
{
    case Positive = 'positive';
    case Negative = 'negative';

    public function label(): string
    {
        return match ($this) {
            self::Positive => 'Positive',
            self::Negative => 'Négative',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Positive => 'green',
            self::Negative => 'red',
        };
    }
}
