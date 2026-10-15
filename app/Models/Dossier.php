<?php

namespace App\Models;

use App\Enums\DossierOrigin;
use App\Enums\DossierStatus;
use App\Enums\ReceptionChannel;
use Database\Factories\DossierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference',
    'origin',
    'subject',
    'description',
    'request_type_id',
    'urgency_level_id',
    'external_requester_id',
    'originating_department_id',
    'created_by',
    'reception_channel',
    'external_reference',
    'received_at',
    'due_at',
    'status',
    'archived_at',
])]
class Dossier extends Model
{
    /** @use HasFactory<DossierFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'sent',
    ];

    /**
     * Résultats des contrôles d'accès déjà calculés pour cette instance
     * (évite de refaire la même requête pour chaque pièce ou réponse affichée).
     *
     * @var array<string, bool>
     */
    public array $accessCache = [];

    public function rememberAccess(string $key, callable $check): bool
    {
        return $this->accessCache[$key] ??= (bool) $check();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => DossierOrigin::class,
            'status' => DossierStatus::class,
            'reception_channel' => ReceptionChannel::class,
            'received_at' => 'datetime',
            'due_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function isExternal(): bool
    {
        return $this->origin === DossierOrigin::External;
    }

    /**
     * Auteur du dossier ou l'un de ses destinataires.
     */
    public function isParticipant(User $user): bool
    {
        return $this->created_by === $user->id
            || $this->rememberAccess('participant:'.$user->id, fn () => $this->relationLoaded('recipients')
                ? $this->recipients->contains('assigned_user_id', $user->id)
                : $this->recipients()->where('assigned_user_id', $user->id)->exists());
    }

    /**
     * Dossiers qu'un utilisateur a le droit de consulter. Source unique de
     * vérité : DossierPolicy::view s'appuie sur ce scope.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('view_all_dossiers')) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user) {
            $visible->where(fn (Builder $linked) => $linked->relatedTo($user));

            if ($user->hasPermission('view_department_dossiers') && $user->department_id) {
                $visible->orWhere(fn (Builder $department) => $department->concerningDepartment($user->department_id));
            }

            if ($user->hasPermission('manage_external_requests')) {
                $visible->orWhere('origin', DossierOrigin::External->value);
            }
        });
    }

    /**
     * Dossiers dont l'utilisateur peut lire l'historique détaillé (indépendant
     * de visibleTo : un administrateur voit les dossiers mais pas leur historique
     * tant que son rôle n'a aucune permission view_*_history).
     */
    #[Scope]
    protected function historyVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('view_all_history')) {
            return $query;
        }

        $byDepartment = $user->hasPermission('view_department_history') && $user->department_id;
        $own = $user->hasPermission('view_own_history');

        if (! $byDepartment && ! $own) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $visible) use ($user, $byDepartment, $own) {
            if ($byDepartment) {
                $visible->orWhere(fn (Builder $department) => $department->concerningDepartment($user->department_id));
            }

            if ($own) {
                $visible->orWhere(fn (Builder $linked) => $linked->relatedTo($user));
            }
        });
    }

    /**
     * Dossiers auxquels l'utilisateur participe personnellement.
     */
    #[Scope]
    protected function relatedTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $linked) => $linked
            ->where('created_by', $user->id)
            ->orWhereHas('recipients', fn (Builder $recipient) => $recipient->where('assigned_user_id', $user->id))
            ->orWhereHas('validations', fn (Builder $validation) => $validation->where('validator_id', $user->id)));
    }

    /**
     * Dossiers émis par un département ou adressés à l'un de ses membres.
     */
    #[Scope]
    protected function concerningDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where(fn (Builder $department) => $department
            ->where('originating_department_id', $departmentId)
            ->orWhereHas('recipients', fn (Builder $recipient) => $recipient->where('department_id', $departmentId)));
    }

    /**
     * @return BelongsTo<RequestType, $this>
     */
    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    /**
     * @return BelongsTo<UrgencyLevel, $this>
     */
    public function urgencyLevel(): BelongsTo
    {
        return $this->belongsTo(UrgencyLevel::class);
    }

    /**
     * @return BelongsTo<ExternalRequester, $this>
     */
    public function externalRequester(): BelongsTo
    {
        return $this->belongsTo(ExternalRequester::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function originatingDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'originating_department_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DossierRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(DossierRecipient::class);
    }

    /**
     * @return BelongsToMany<Department, $this>
     */
    public function recipientDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'dossier_recipients')
            ->using(DossierRecipient::class)
            ->withPivot(['id', 'recipient_type', 'status', 'due_at', 'assigned_user_id'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<DossierValidation, $this>
     */
    public function validations(): HasMany
    {
        return $this->hasMany(DossierValidation::class);
    }

    /**
     * @return HasMany<Response, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Dernière version de chaque document.
     *
     * @return HasMany<Document, $this>
     */
    public function currentDocuments(): HasMany
    {
        return $this->documents()->active();
    }

    /**
     * @return HasMany<DossierTransmission, $this>
     */
    public function transmissions(): HasMany
    {
        return $this->hasMany(DossierTransmission::class);
    }

    /**
     * @return HasMany<Reminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /**
     * @return HasMany<DossierActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(DossierActivityLog::class);
    }
}
