<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Références uniques DOS-AAAA-NNNNNN. La ligne de l'année est verrouillée
 * (SELECT … FOR UPDATE) : deux envois simultanés ne peuvent pas obtenir le
 * même numéro. À appeler dans la transaction de création du dossier.
 */
class DossierReferenceGenerator
{
    public function next(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        DB::table('dossier_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

        $number = DB::transaction(function () use ($year) {
            $current = (int) DB::table('dossier_sequences')->where('year', $year)->lockForUpdate()->value('last_number');
            DB::table('dossier_sequences')->where('year', $year)->update(['last_number' => $current + 1]);

            return $current + 1;
        });

        $prefix = Setting::valueFor('dossiers.reference_prefix', 'DOS');

        return sprintf('%s-%d-%06d', $prefix, $year, $number);
    }
}
