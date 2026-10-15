<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dossiers:check-deadlines')]
#[Description('Alerte les destinataires dont l’échéance est aujourd’hui ou dépassée (sans doublon).')]
class CheckDossierDeadlines extends Command
{
    public function handle(ReminderService $reminders): int
    {
        $counts = $reminders->checkDeadlines();

        $this->info("Échéances du jour : {$counts['due_today']} · Délais dépassés : {$counts['overdue']}");

        return self::SUCCESS;
    }
}
