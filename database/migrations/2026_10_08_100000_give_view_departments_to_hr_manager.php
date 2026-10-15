<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::query()->where('name', 'view_departments')->first();

        if ($permission) {
            Role::query()->where('name', Role::HR_MANAGER)->first()
                ?->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    public function down(): void
    {
        $permission = Permission::query()->where('name', 'view_departments')->first();

        if ($permission) {
            Role::query()->where('name', Role::HR_MANAGER)->first()?->permissions()->detach($permission->id);
        }
    }
};
