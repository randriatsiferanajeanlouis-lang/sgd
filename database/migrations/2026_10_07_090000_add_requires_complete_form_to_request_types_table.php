<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * « Formulaire complet » : pour ces types de demande, tous les champs du
     * formulaire deviennent obligatoires (ex. Sponsoring : organisme, contact, description).
     */
    public function up(): void
    {
        Schema::table('request_types', function (Blueprint $table) {
            $table->boolean('requires_complete_form')->default(false)->after('scope');
        });

        DB::table('request_types')->where('code', 'SPONSORING')->update(['requires_complete_form' => true]);
    }

    public function down(): void
    {
        Schema::table('request_types', function (Blueprint $table) {
            $table->dropColumn('requires_complete_form');
        });
    }
};
