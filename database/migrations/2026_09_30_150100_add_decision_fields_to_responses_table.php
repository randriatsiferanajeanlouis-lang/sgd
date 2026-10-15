<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Décision d'un responsable sur une demande externe (positive/négative,
     * justification, recommandation) et canal de la réponse officielle du RH.
     */
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->string('decision', 20)->nullable()->after('type');
            $table->text('justification')->nullable()->after('content');
            $table->text('recommendation')->nullable()->after('justification');
            $table->string('channel', 30)->nullable()->after('is_final');
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropColumn(['decision', 'justification', 'recommendation', 'channel']);
        });
    }
};
