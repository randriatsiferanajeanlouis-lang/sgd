<?php

namespace App\Models;

use App\Enums\RecipientStatus;
use App\Enums\RecipientType;
use App\Enums\ValidationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Département destinataire d'un dossier, avec son statut propre.
 * Modèle pivot à identifiant auto-incrémenté : il porte ses propres
 * réponses, transmissions et relances. Les clés étrangères des hasMany
 * sont explicites car Pivot::getForeignKey() ne suit pas la convention.
 */
#[Fillable([
    'dossier_id',
    'department_id',
    'assigned_user_id',
    'recipient_type',
    'status',
    'due_at',
    'read_at',
    'answered_at',
    'archived_at',
])]
class DossierRecipient extends Pivot
{
    protected $table = 'dossier_recipients';

    public $incrementing = true;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'received',
        'recipient_type' => 'action',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recipient_type' => RecipientType::class,
            'status' => RecipientStatus::class,
            'due_at' => 'datetime',
            'read_at' => 'datetime',
            'answered_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Une demande de validation au responsable est en cours : le destinataire
     * ne peut pas répondre avant la décision.
     */
    public function hasPendingValidation(): bool
    {
        return $this->validations()->where('status', ValidationStatus::Pending->value)->exists();
    }

    /**
     * @return HasMany<DossierValidation, $this>
     */
    public function validations(): HasMany
    {
        return $this->hasMany(DossierValidation::class, 'dossier_recipient_id');
    }

    /**
     * @return BelongsTo<Dossier, $this>
     */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return HasMany<Response, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class, 'dossier_recipient_id');
    }

    /**
     * @return HasMany<DossierTransmission, $this>
     */
    public function transmissions(): HasMany
    {
        return $this->hasMany(DossierTransmission::class, 'dossier_recipient_id');
    }

    /**
     * @return HasMany<Reminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'dossier_recipient_id');
    }
}
