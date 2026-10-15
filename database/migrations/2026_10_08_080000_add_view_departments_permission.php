<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['name' => 'view_departments'],
            ['group' => 'Administration', 'label' => 'Consulter la liste des départements (lecture seule)'],
        );

        Role::query()->where('name', Role::DEPARTMENT_HEAD)->first()
            ?->permissions()->syncWithoutDetaching([$permission->id]);
    }

    public function down(): void
    {
        Permission::query()->where('name', 'view_departments')->delete();
    }
};
