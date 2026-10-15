<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relances (automatiques ou manuelles) adressées à un destinataire.
     */
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->restrictOnDelete();
            $table->foreignId('dossier_recipient_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20)->default('automatic');
            $table->string('channel', 20)->default('in_app');
            $table->text('message')->nullable();
            $table->timestamp('scheduled_at')->useCurrent();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['sent_at', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
