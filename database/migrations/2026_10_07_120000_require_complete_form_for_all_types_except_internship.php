<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Toutes les demandes exigent un formulaire complet, sauf la demande de
     * stage (réglable ensuite type par type dans Paramètres → Types de demandes).
     */
    public function up(): void
    {
        DB::table('request_types')->where('code', '!=', 'STAGE')->update(['requires_complete_form' => true]);
        DB::table('request_types')->where('code', 'STAGE')->update(['requires_complete_form' => false]);
    }

    public function down(): void
    {
        DB::table('request_types')->where('code', '!=', 'SPONSORING')->update(['requires_complete_form' => false]);
    }
};
