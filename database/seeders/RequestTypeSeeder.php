<?php

namespace Database\Seeders;

use App\Enums\RequestScope;
use App\Models\RequestType;
use Illuminate\Database\Seeder;

class RequestTypeSeeder extends Seeder
{
    /**
     * Types initiaux. Crée seulement les types manquants : un type modifié
     * ou désactivé depuis l'écran Paramètres n'est jamais écrasé.
     */
    public function run(): void
    {
        $types = [
            ['EMPLOI', 'Demande d’emploi', RequestScope::External],
            ['STAGE', 'Demande de stage', RequestScope::External],
            ['INFORMATION', 'Demande d’information', RequestScope::Both],
            ['ADMINISTRATIVE', 'Demande administrative', RequestScope::Both],
            ['SPONSORING', 'Sponsoring', RequestScope::External],
            ['INTERVENTION', 'Intervention', RequestScope::Both],
            ['RECLAMATION', 'Réclamation', RequestScope::External],
            ['NOTE_SERVICE', 'Note de service', RequestScope::Internal],
            ['MATERIEL', 'Demande de matériel', RequestScope::Internal],
            ['AUTRE', 'Autre', RequestScope::Both],
        ];

        foreach ($types as $order => [$code, $name, $scope]) {
            RequestType::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'scope' => $scope, 'requires_complete_form' => $code !== 'STAGE', 'is_active' => true, 'sort_order' => $order + 1],
            );
        }
    }
}
