<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Libellé d'échéance en jours calendaires : « Échéance dans 3 jours »,
 * « Échéance demain », « Échéance aujourd’hui », « Délai dépassé de 2 jours ».
 * Un délai dépassé est une alerte : il ne modifie jamais le statut.
 */
final class Deadline
{
    /**
     * @return array{label: string, color: string, overdue: bool}|null
     */
    public static function describe(?CarbonInterface $dueAt, bool $isClosed = false, ?CarbonInterface $now = null): ?array
    {
        if ($dueAt === null || $isClosed) {
            return null;
        }

        $now ??= now();
        $days = (int) $now->copy()->startOfDay()->diffInDays($dueAt->copy()->startOfDay(), false);

        if ($days < 0 || ($days === 0 && $dueAt->lessThan($now))) {
            $late = max(1, abs($days));

            return ['label' => 'Délai dépassé de '.$late.' jour'.($late > 1 ? 's' : ''), 'color' => 'red', 'overdue' => true];
        }

        return match (true) {
            $days === 0 => ['label' => 'Échéance aujourd’hui', 'color' => 'amber', 'overdue' => false],
            $days === 1 => ['label' => 'Échéance demain', 'color' => 'amber', 'overdue' => false],
            default => ['label' => "Échéance dans {$days} jours", 'color' => 'slate', 'overdue' => false],
        };
    }
}
