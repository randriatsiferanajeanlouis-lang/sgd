<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le compte administrateur est un super administrateur indépendant :
 * il n'appartient à aucun département et ne dirige aucun département.
 */
return new class extends Migration
{
    public function up(): void
    {
        $adminRoleId = DB::table('roles')->where('name', Role::ADMIN)->value('id');

        if ($adminRoleId === null) {
            return;
        }

        $adminIds = DB::table('users')->where('role_id', $adminRoleId)->pluck('id');

        DB::table('departments')->whereIn('manager_id', $adminIds)->update(['manager_id' => null]);
        DB::table('users')->whereIn('id', $adminIds)->update(['department_id' => null]);
    }

    public function down(): void
    {
        // Rattachement d'origine non conservé : à refaire à la main si besoin.
    }
};
