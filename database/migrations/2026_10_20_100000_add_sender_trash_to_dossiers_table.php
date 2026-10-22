<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corbeille de l'expéditeur : le dossier disparaît de son espace sans être
 * effacé, les destinataires et l'historique le conservent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->timestamp('trashed_by_sender_at')->nullable()->after('archived_at');
            $table->timestamp('purged_by_sender_at')->nullable()->after('trashed_by_sender_at');
            $table->index(['created_by', 'trashed_by_sender_at']);
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropIndex(['created_by', 'trashed_by_sender_at']);
            $table->dropColumn(['trashed_by_sender_at', 'purged_by_sender_at']);
        });
    }
};
