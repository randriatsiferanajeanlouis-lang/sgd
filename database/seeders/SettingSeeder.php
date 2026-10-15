<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Crée les paramètres manquants sans écraser une valeur déjà modifiée.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'external_requests.entry_role',
                'value' => Role::HR_MANAGER,
                'type' => 'string',
                'group' => 'dossiers',
                'label' => 'Rôle porte d’entrée des demandes externes',
            ],
            [
                'key' => 'external_requests.entry_department',
                'value' => Department::HR_DEPARTMENT_CODE,
                'type' => 'string',
                'group' => 'dossiers',
                'label' => 'Département du service RH (porte d’entrée des demandes externes)',
            ],
            [
                'key' => 'dossiers.reference_prefix',
                'value' => 'DOS',
                'type' => 'string',
                'group' => 'dossiers',
                'label' => 'Préfixe des références de dossier',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::query()->firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
