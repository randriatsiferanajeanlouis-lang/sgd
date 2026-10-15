<?php

namespace App\Services;

use App\Models\Dossier;
use App\Models\DossierActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Point d'entrée unique de l'historique des dossiers (ajout seul).
 * Chaque entrée conserve : utilisateur, date/heure, action, élément concerné,
 * ancienne valeur, nouvelle valeur et commentaire.
 */
class DossierHistory
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $properties
     */
    public function record(
        Dossier $dossier,
        string $action,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $comment = null,
        ?array $properties = null,
    ): DossierActivityLog {
        $user = Auth::user();
        $request = request();

        return DossierActivityLog::query()->create([
            'dossier_id' => $dossier->id,
            'user_id' => $user?->id,
            'department_id' => $user?->department_id,
            'action' => $action,
            'description' => __('history.'.$action),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'comment' => $comment,
            'properties' => $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 250, ''),
        ]);
    }
}
