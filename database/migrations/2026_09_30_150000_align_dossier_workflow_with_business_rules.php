<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aligne le schéma sur les règles métier :
     *  - le destinataire est un utilisateur (département → utilisateur) : plusieurs
     *    personnes d'un même département peuvent recevoir le même dossier ;
     *  - statuts Envoyé / Reçu / En cours / Répondu / Archivé (pas d'« En attente ») ;
     *  - séquence annuelle pour les références DOS-AAAA-NNNNNN ;
     *  - l'historique conserve ancienne valeur, nouvelle valeur et commentaire.
     */
    public function up(): void
    {
        Schema::table('dossier_recipients', function (Blueprint $table) {
            $table->index('dossier_id', 'dossier_recipients_dossier_id_index');
        });

        Schema::table('dossier_recipients', function (Blueprint $table) {
            $table->dropUnique(['dossier_id', 'department_id']);
            $table->dropForeign(['assigned_user_id']);
        });

        Schema::table('dossier_recipients', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable(false)->change();
            $table->foreign('assigned_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['dossier_id', 'assigned_user_id']);
            $table->index(['assigned_user_id', 'status']);
            $table->string('status', 30)->default('received')->change();
            $table->timestamp('archived_at')->nullable()->after('answered_at');
            $table->dropColumn('closed_at');
        });

        Schema::table('dossiers', function (Blueprint $table) {
            $table->string('status', 30)->default('sent')->change();
            $table->dropColumn('closed_at');
        });

        Schema::create('dossier_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        Schema::table('dossier_activity_logs', function (Blueprint $table) {
            $table->json('old_values')->nullable()->after('subject_id');
            $table->json('new_values')->nullable()->after('old_values');
            $table->text('comment')->nullable()->after('new_values');
        });

        DB::table('dossier_recipients')->whereIn('status', ['pending'])->update(['status' => 'received']);
        DB::table('dossier_recipients')->where('status', 'returned')->update(['status' => 'in_progress']);
        DB::table('dossier_recipients')->where('status', 'closed')->update(['status' => 'archived']);
        DB::table('dossiers')->whereIn('status', ['registered', 'transmitted'])->update(['status' => 'sent']);
        DB::table('dossiers')->where('status', 'partially_answered')->update(['status' => 'in_progress']);
        DB::table('dossiers')->where('status', 'closed')->update(['status' => 'archived']);
    }

    public function down(): void
    {
        Schema::table('dossier_activity_logs', function (Blueprint $table) {
            $table->dropColumn(['old_values', 'new_values', 'comment']);
        });

        Schema::dropIfExists('dossier_sequences');

        Schema::table('dossiers', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable();
            $table->string('status', 30)->default('registered')->change();
        });

        Schema::table('dossier_recipients', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
        });

        Schema::table('dossier_recipients', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable();
            $table->dropColumn('archived_at');
            $table->string('status', 30)->default('pending')->change();
            $table->dropIndex(['assigned_user_id', 'status']);
            $table->dropUnique(['dossier_id', 'assigned_user_id']);
        });

        Schema::table('dossier_recipients', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->change();
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['dossier_id', 'department_id']);
            $table->dropIndex('dossier_recipients_dossier_id_index');
        });
    }
};
