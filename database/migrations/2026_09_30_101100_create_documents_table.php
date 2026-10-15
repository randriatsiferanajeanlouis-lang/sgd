<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documents versionnés : toutes les versions d'un même document
     * partagent un document_key. Une nouvelle version ne remplace jamais
     * le fichier précédent.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->restrictOnDelete();
            $table->foreignId('response_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('document_key');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('previous_version_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->boolean('is_current')->default(true);
            $table->string('category', 30)->default('attachment');
            $table->string('original_name');
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->char('checksum', 64)->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['document_key', 'version']);
            $table->index(['dossier_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
