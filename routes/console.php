<?php

use Illuminate\Support\Facades\Schedule;

/*
| Alertes d'échéance : toutes les heures ; la commande ne renvoie jamais
| deux fois la même alerte. Nécessite `php artisan schedule:work` (ou une
| tâche planifiée Windows appelant `php artisan schedule:run` chaque minute).
*/
Schedule::command('dossiers:check-deadlines')->hourly()->withoutOverlapping();
