<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demandeurs externes (particuliers ou organisations), réutilisables
     * d'un dossier à l'autre.
     */
    public function up(): void
    {
        Schema::create('external_requesters', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('person');
            $table->string('full_name')->index();
            $table->string('organization')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 50)->nullable()->index();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_requesters');
    }
};
