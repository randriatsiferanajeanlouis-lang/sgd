<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Délai toujours exprimé en heures, pour un calcul d'échéance uniforme.
     */
    public function up(): void
    {
        Schema::create('urgency_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 50)->unique();
            $table->unsignedInteger('response_delay_hours');
            $table->string('scope', 20)->default('external')->index();
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urgency_levels');
    }
};
