<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Départements initiaux. Crée seulement les départements manquants :
     * un département modifié depuis l'administration n'est jamais écrasé.
     */
    public function run(): void
    {
        $departments = [
            ['code' => 'ADMINISTRATION', 'name' => 'Administration'],
            [
                'code' => Department::HR_DEPARTMENT_CODE,
                'name' => 'RSE',
                'description' => 'Responsabilité sociétale ; inclut les Ressources Humaines, porte d’entrée des demandes externes.',
            ],
            ['code' => 'COLLECTE', 'name' => 'Collecte'],
            ['code' => 'PRODUCTION', 'name' => 'Production'],
            ['code' => 'ARMEMENT', 'name' => 'Armement'],
            ['code' => 'TECHNIQUE', 'name' => 'Technique'],
        ];

        foreach ($departments as $order => $department) {
            Department::query()->firstOrCreate(
                ['code' => $department['code']],
                [
                    'name' => $department['name'],
                    'description' => $department['description'] ?? null,
                    'is_active' => true,
                    'sort_order' => $order + 1,
                ],
            );
        }
    }
}
