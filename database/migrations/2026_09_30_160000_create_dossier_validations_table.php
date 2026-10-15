<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Validation hiérarchique : un destinataire transmet le dossier à son
     * responsable, qui valide ou refuse avant la réponse au demandeur.
     */
    public function up(): void
    {
        Schema::create('dossier_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->restrictOnDelete();
            $table->foreignId('dossier_recipient_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('validator_id')->constrained('users')->restrictOnDelete();
            $table->text('request_comment')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('decision_comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['validator_id', 'status']);
            $table->index(['dossier_recipient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossier_validations');
    }
};
