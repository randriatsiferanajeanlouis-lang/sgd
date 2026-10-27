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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'reference',
    'origin',
    'subject',
    'description',
    'request_type_id',
    'request_type_other',
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
    /**
     * Type affiché : le texte saisi quand l'expéditeur a choisi « Autre », sinon le nom du type.
     */
    public function typeLabel(): string
    {
        return filled($this->request_type_other) ? $this->request_type_other : (string) $this->requestType?->name;
    }

    protected function casts(): array
    {
        return [
            'origin' => DossierOrigin::class,
            'status' => DossierStatus::class,
            'reception_channel' => ReceptionChannel::class,
            'received_at' => 'datetime',
            'due_at' => 'datetime',
            'archived_at' => 'datetime',
            'trashed_by_sender_at' => 'datetime',
            'purged_by_sender_at' => 'datetime',
        ];
    }

    /**
     * Mis à la corbeille par son expéditeur (restaurable tant qu'il n'est pas supprimé définitivement).
     */
    public function isTrashedBySender(): bool
    {
        return $this->trashed_by_sender_at !== null;
    }

    /**
     * Retrait du dossier par son expéditeur, pour le suivi de l'administrateur :
     * le dossier n'est jamais effacé, il reste consultable et tracé.
     *
     * @return array{state: string, label: string, at: string}|null
     */
    public function senderRemoval(): ?array
    {
        if ($this->purged_by_sender_at) {
            return ['state' => 'purged', 'label' => 'Supprimé définitivement par l’expéditeur', 'at' => $this->purged_by_sender_at->format('d/m/Y H:i')];
        }

        if ($this->trashed_by_sender_at) {
            return ['state' => 'trashed', 'label' => 'Dans la corbeille de l’expéditeur', 'at' => $this->trashed_by_sender_at->format('d/m/Y H:i')];
        }

        return null;
    }

    /** Nombre maximal de caractères de l'objet affichés dans les listes. */
    public const SUBJECT_LIST_CHARS = 15;

    /**
     * Objet abrégé pour les listes : 15 caractères au plus, suivis de « ... »
     * seulement s'il est plus long. La coupe se fait sur le dernier mot entier
     * (un mot de plus de 15 caractères est coupé). Affichage uniquement :
     * l'objet enregistré n'est jamais modifié et reste complet sur la fiche du
     * dossier et dans les exports. Même règle côté React : resources/js/lib/text.js.
     */
    public function shortSubject(int $chars = self::SUBJECT_LIST_CHARS): string
    {
        $subject = trim(preg_replace('/\s+/u', ' ', (string) $this->subject));

        if (mb_strlen($subject) <= $chars) {
            return $subject;
        }

        $cut = mb_substr($subject, 0, $chars);
        $space = mb_strrpos($cut, ' ');

        // Mot coupé au milieu : on revient au dernier mot entier, s'il y en a un.
        if (! preg_match('/^[\s,;:.!?)]/u', mb_substr($subject, $chars, 1)) && $space !== false) {
            $cut = mb_substr($cut, 0, $space);
        }

        return preg_replace('/[\s,;:.\-–—]+$/u', '', $cut).' ...';
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
        })
            // Un dossier mis à la corbeille par son expéditeur quitte aussi les listes
            // de département et d'organisme de cet expéditeur.
            ->where(fn (Builder $own) => $own->where('created_by', '!=', $user->id)->orWhereNull('trashed_by_sender_at'));
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
            ->where(fn (Builder $sent) => $sent->sentBy($user))
            ->orWhereHas('recipients', fn (Builder $recipient) => $recipient->where('assigned_user_id', $user->id))
            ->orWhereHas('validations', fn (Builder $validation) => $validation->where('validator_id', $user->id)));
    }

    /**
     * Dossiers envoyés par l'utilisateur, hors corbeille : la corbeille de
     * l'expéditeur retire le dossier de son espace seulement.
     */
    #[Scope]
    protected function sentBy(Builder $query, User $user): Builder
    {
        return $query->where('created_by', $user->id)->whereNull('trashed_by_sender_at');
    }

    /**
     * Corbeille de l'expéditeur : dossiers retirés de son espace, encore restaurables.
     */
    #[Scope]
    protected function inSenderTrash(Builder $query, User $user): Builder
    {
        return $query->where('created_by', $user->id)->whereNotNull('trashed_by_sender_at')->whereNull('purged_by_sender_at');
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

    /**
     * Dernière action enregistrée (colonne « Modifié le / par »).
     *
     * @return HasOne<DossierActivityLog, $this>
     */
    public function latestActivity(): HasOne
    {
        return $this->hasOne(DossierActivityLog::class)->latestOfMany();
    }
}
