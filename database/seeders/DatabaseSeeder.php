<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de référence, puis comptes initiaux (admin et RH).
     */
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            RoleSeeder::class,
            RequestTypeSeeder::class,
            UrgencyLevelSeeder::class,
            SettingSeeder::class,
            InitialAccountSeeder::class,
        ]);
    }
}
