<?php

namespace App\Enums;

/**
 * Motif d'une relance : manuelle, ou alerte automatique d'échéance (une
 * seule par traitement et par motif).
 */
enum ReminderReason: string
{
    case Manual = 'manual';
    case DeadlineToday = 'deadline_today';
    case DeadlineOverdue = 'deadline_overdue';
}
