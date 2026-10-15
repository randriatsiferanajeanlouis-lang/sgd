<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plusieurs départements destinataires par dossier, chacun avec son
     * propre statut et sa propre échéance.
     */
    public function up(): void
    {
        Schema::create('dossier_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_type', 20)->default('action');
            $table->string('status', 30)->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['dossier_id', 'department_id']);
            $table->index(['department_id', 'status']);
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossier_recipients');
    }
};
