<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chaque envoi d'un dossier vers un département (transmission initiale,
     * réacheminement, renvoi) est conservé.
     */
    public function up(): void
    {
        Schema::create('dossier_transmissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->restrictOnDelete();
            $table->foreignId('dossier_recipient_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->foreignId('to_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('transmitted_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 30)->default('initial');
            $table->text('comment')->nullable();
            $table->timestamp('transmitted_at')->useCurrent();
            $table->timestamps();

            $table->index(['dossier_id', 'transmitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossier_transmissions');
    }
};
