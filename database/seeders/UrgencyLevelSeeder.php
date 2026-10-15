<?php

namespace Database\Seeders;

use App\Enums\RequestScope;
use App\Models\UrgencyLevel;
use Illuminate\Database\Seeder;

class UrgencyLevelSeeder extends Seeder
{
    /**
     * Niveaux d'urgence des demandes externes (délai de réponse en heures).
     */
    public function run(): void
    {
        $levels = [
            ['TRES_URGENT', 'Très urgent', 24, '#dc2626'],
            ['URGENT', 'Urgent', 72, '#f59e0b'],
            ['NORMAL', 'Normal', 168, '#2563eb'],
        ];

        foreach ($levels as $order => [$code, $name, $hours, $color]) {
            UrgencyLevel::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'response_delay_hours' => $hours,
                    'scope' => RequestScope::External,
                    'color' => $color,
                    'is_active' => true,
                    'sort_order' => $order + 1,
                ],
            );
        }
    }
}
