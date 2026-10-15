<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un dossier est interne (émis par un département) ou externe
     * (demandeur extérieur enregistré par le RH). La contrainte CHECK
     * garantit la cohérence entre l'origine et la provenance.
     */
    public function up(): void
    {
        Schema::create('dossiers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('origin', 20);
            $table->string('subject');
            $table->text('description')->nullable();
            $table->foreignId('request_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('urgency_level_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('external_requester_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('originating_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('reception_channel', 30)->nullable();
            $table->string('external_reference')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('status', 30)->default('registered');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['origin', 'status']);
            $table->index(['status', 'due_at']);
        });

        DB::statement("ALTER TABLE dossiers ADD CONSTRAINT dossiers_origin_source_check CHECK (
            (origin = 'external' AND external_requester_id IS NOT NULL)
            OR (origin = 'internal' AND originating_department_id IS NOT NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('dossiers');
    }
};
