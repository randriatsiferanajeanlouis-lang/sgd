<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal d'activité en ajout seul : des triggers refusent toute
     * modification ou suppression au niveau de la base, en plus de la
     * protection du modèle DossierActivityLog.
     */
    public function up(): void
    {
        Schema::create('dossier_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action', 50)->index();
            $table->text('description')->nullable();
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['dossier_id', 'created_at']);
        });

        DB::unprepared("CREATE TRIGGER dossier_activity_logs_no_update BEFORE UPDATE ON dossier_activity_logs
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historique en ajout seul : modification interdite.'");

        DB::unprepared("CREATE TRIGGER dossier_activity_logs_no_delete BEFORE DELETE ON dossier_activity_logs
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historique en ajout seul : suppression interdite.'");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS dossier_activity_logs_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS dossier_activity_logs_no_delete');
        Schema::dropIfExists('dossier_activity_logs');
    }
};
