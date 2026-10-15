<?php

namespace App\Enums;

/**
 * Nature d'une réponse enregistrée sur un dossier.
 */
enum ResponseType: string
{
    case Answer = 'answer';
    case ComplementRequest = 'complement_request';
    case Note = 'note';
    case FinalAnswer = 'final_answer';

    public function label(): string
    {
        return match ($this) {
            self::Answer => 'Réponse',
            self::ComplementRequest => 'Demande de complément',
            self::Note => 'Note interne',
            self::FinalAnswer => 'Réponse finale au demandeur',
        };
    }
}
