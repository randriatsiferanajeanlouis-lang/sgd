<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motif de la relance (manual, deadline_today, deadline_overdue) : sert à
     * ne jamais envoyer deux fois la même alerte automatique.
     */
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('reason', 30)->default('manual')->after('channel');
            $table->index(['dossier_recipient_id', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropIndex(['dossier_recipient_id', 'reason']);
            $table->dropColumn('reason');
        });
    }
};
