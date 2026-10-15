<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Aligne les types déjà présents sur la liste initiale des demandes
     * externes. Aucun type n'est supprimé (des dossiers peuvent y être liés) :
     * les types hors liste sont seulement désactivés.
     */
    public function up(): void
    {
        DB::table('request_types')->where('code', 'EMPLOI')->update(['name' => 'Demande d’emploi', 'sort_order' => 1]);
        DB::table('request_types')->where('code', 'STAGE')->update(['sort_order' => 2]);
        DB::table('request_types')->where('code', 'INFORMATION')->update(['sort_order' => 3]);
        DB::table('request_types')->where('code', 'ADMINISTRATIVE')->update(['sort_order' => 4]);
        DB::table('request_types')->where('code', 'SPONSORING')->update(['sort_order' => 5]);
        DB::table('request_types')->where('code', 'INTERVENTION')->update(['name' => 'Intervention', 'scope' => 'both', 'sort_order' => 6]);
        DB::table('request_types')->where('code', 'RECLAMATION')->update(['sort_order' => 7]);
        DB::table('request_types')->where('code', 'NOTE_SERVICE')->update(['sort_order' => 8]);
        DB::table('request_types')->where('code', 'MATERIEL')->update(['sort_order' => 9]);
        DB::table('request_types')->where('code', 'AUTRE')->update(['sort_order' => 10]);

        DB::table('request_types')
            ->whereIn('code', ['PARTENARIAT', 'INVITATION', 'COMMERCIALE', 'FOURNISSEUR', 'INSTITUTIONNEL'])
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        DB::table('request_types')
            ->whereIn('code', ['PARTENARIAT', 'INVITATION', 'COMMERCIALE', 'FOURNISSEUR', 'INSTITUTIONNEL'])
            ->update(['is_active' => true]);
    }
};
