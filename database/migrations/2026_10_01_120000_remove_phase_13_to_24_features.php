<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retour au périmètre des phases 1 à 12 : retire les structures créées
     * uniquement pour les phases 13 à 24 (réouverture du dossier entier,
     * sauvegardes, réglages avancés). Aucune donnée métier n'est touchée :
     * dossiers, historiques, documents, notifications et journal d'audit
     * (y compris les traces des sauvegardes) sont conservés.
     */
    public function up(): void
    {
        if (Schema::hasColumn('dossiers', 'reopen_count')) {
            Schema::table('dossiers', function (Blueprint $table) {
                $table->dropColumn(['reopened_at', 'reopen_count']);
            });
        }

        Schema::dropIfExists('backups');

        DB::table('permissions')->where('name', 'manage_backups')->delete();
        DB::table('settings')->whereIn('key', ['uploads.max_kilobytes', 'uploads.max_files'])->delete();
    }

    /**
     * Restaure les structures (vides) : les données retirées ne peuvent pas
     * être reconstituées, une copie existe dans la sauvegarde d'avant rollback.
     */
    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->timestamp('reopened_at')->nullable()->after('archived_at');
            $table->unsignedSmallInteger('reopen_count')->default(0)->after('reopened_at');
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->nullable();
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('size')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->unsignedInteger('file_count')->default(0);
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }
};
