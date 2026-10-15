<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réponses d'un département destinataire, ou réponse finale envoyée
     * au demandeur.
     */
    public function up(): void
    {
        Schema::create('responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->restrictOnDelete();
            $table->foreignId('dossier_recipient_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 30)->default('answer');
            $table->text('content');
            $table->boolean('is_final')->default(false);
            $table->timestamp('sent_to_requester_at')->nullable();
            $table->timestamps();

            $table->index(['dossier_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responses');
    }
};
