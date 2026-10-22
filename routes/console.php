<?php

use Illuminate\Support\Facades\Schedule;

/*
| Alertes d'échéance : toutes les heures ; la commande ne renvoie jamais
| deux fois la même alerte. Nécessite `php artisan schedule:work` (ou une
| tâche planifiée Windows appelant `php artisan schedule:run` chaque minute).
*/
Schedule::command('dossiers:check-deadlines')->hourly()->withoutOverlapping();

/*
| Sauvegarde de la base deux fois par jour (midi et fin de journée) ; les plus
| anciennes sont supprimées (BACKUP_KEEP, défaut 14). Dossier : BACKUP_PATH.
*/
Schedule::command('db:backup')->twiceDaily(12, 17)->withoutOverlapping();
