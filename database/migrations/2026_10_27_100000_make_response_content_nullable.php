<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une réponse interne peut être un simple choix Positive / Négative, sans texte.
     */
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->text('content')->nullable(false)->change();
        });
    }
};
