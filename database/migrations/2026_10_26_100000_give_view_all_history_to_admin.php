<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * L'administrateur trace toute l'activité du système : il lit l'historique
     * de tous les dossiers (il reste en lecture seule sur les dossiers).
     */
    public function up(): void
    {
        $permission = Permission::query()->where('name', 'view_all_history')->first();

        if ($permission) {
            Role::query()->where('name', Role::ADMIN)->first()
                ?->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    public function down(): void
    {
        $permission = Permission::query()->where('name', 'view_all_history')->first();

        if ($permission) {
            Role::query()->where('name', Role::ADMIN)->first()?->permissions()->detach($permission->id);
        }
    }
};
