<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Statut documentaire ACTIVE / REPLACED / CANCELLED à la place du simple
     * booléen is_current. Une version remplacée ou annulée reste enregistrée
     * et tracée ; seule la version active est proposée au téléchargement.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('version');
            $table->timestamp('cancelled_at')->nullable()->after('uploaded_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
        });

        DB::table('documents')->where('is_current', false)->update(['status' => 'replaced']);

        Schema::table('documents', function (Blueprint $table) {
            $table->index(['dossier_id', 'status']);
            $table->dropIndex(['dossier_id', 'is_current']);
            $table->dropColumn('is_current');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_current')->default(true)->after('previous_version_id');
            $table->index(['dossier_id', 'is_current']);
        });

        DB::table('documents')->where('status', '!=', 'active')->update(['is_current' => false]);

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['dossier_id', 'status']);
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['status', 'cancelled_at', 'cancellation_reason']);
        });
    }
};
