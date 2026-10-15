<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Point d'entrée unique pour tracer les actions hors dossier
 * (comptes, départements, rôles, paramètres).
 */
class AuditTrail
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        string $action,
        ?Model $target = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $comment = null,
        ?Department $department = null,
    ): AuditLog {
        $request = request();

        return AuditLog::query()->create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'department_id' => $department?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'comment' => $comment,
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 250, ''),
        ]);
    }
}
