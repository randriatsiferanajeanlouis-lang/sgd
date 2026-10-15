<?php

namespace App\Enums;

/**
 * Canal d'envoi d'une relance.
 */
enum ReminderChannel: string
{
    case InApp = 'in_app';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::InApp => 'Notification',
            self::Email => 'E-mail',
        };
    }
}
