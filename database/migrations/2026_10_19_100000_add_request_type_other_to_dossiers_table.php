<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Type de demande saisi librement quand l'expéditeur choisit « Autre ».
     */
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->string('request_type_other', 150)->nullable()->after('request_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropColumn('request_type_other');
        });
    }
};
