<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Historique d'un dossier, en ajout seul : toute modification ou
 * suppression est refusée ici et par les triggers de la base.
 */
#[Fillable([
    'dossier_id',
    'user_id',
    'department_id',
    'action',
    'description',
    'subject_type',
    'subject_id',
    'old_values',
    'new_values',
    'comment',
    'properties',
    'ip_address',
    'user_agent',
])]
class DossierActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('L’historique d’un dossier ne peut pas être modifié.'));
        static::deleting(fn () => throw new LogicException('L’historique d’un dossier ne peut pas être supprimé.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'properties' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Dossier, $this>
     */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Élément concerné par l'action (réponse, document, transmission...).
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
