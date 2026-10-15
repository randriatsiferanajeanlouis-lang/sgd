<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fiche complète du personnel. La colonne name est conservée comme nom
     * d'affichage (« Prénom Nom »), recalculée par le modèle User.
     * replaces_user_id relie le nouveau compte créé lors d'un changement de
     * département à l'ancien compte désactivé.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('job_title')->nullable()->after('role_id');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('photo_path')->nullable()->after('phone');
            $table->string('theme', 10)->nullable()->after('last_login_at');
            $table->foreignId('replaces_user_id')->nullable()->after('theme')->constrained('users')->restrictOnDelete();

            $table->index(['department_id', 'is_active']);
            $table->index(['last_name', 'first_name']);
        });

        DB::table('users')->whereNull('last_name')->update(['last_name' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_name', 'first_name']);
            $table->dropConstrainedForeignId('replaces_user_id');
            $table->dropColumn(['first_name', 'last_name', 'job_title', 'phone', 'photo_path', 'theme']);
        });
    }
};
