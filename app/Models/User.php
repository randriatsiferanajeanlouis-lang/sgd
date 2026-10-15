<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'first_name',
    'last_name',
    'username',
    'email',
    'phone',
    'photo_path',
    'password',
    'department_id',
    'role_id',
    'job_title',
    'is_active',
    'must_change_password',
    'last_login_at',
    'theme',
    'replaces_user_id',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * name reste le nom d'affichage « Prénom Nom », dérivé des deux champs.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            $fullName = trim($user->first_name.' '.$user->last_name);

            if ($fullName !== '') {
                $user->name = $fullName;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Ancien compte remplacé lors d'un changement de département.
     *
     * @return BelongsTo<User, $this>
     */
    public function previousAccount(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replaces_user_id');
    }

    /**
     * @return HasMany<Dossier, $this>
     */
    public function createdDossiers(): HasMany
    {
        return $this->hasMany(Dossier::class, 'created_by');
    }

    /**
     * Traitements reçus : une ligne par dossier dont l'utilisateur est destinataire.
     *
     * @return HasMany<DossierRecipient, $this>
     */
    public function assignedRecipients(): HasMany
    {
        return $this->hasMany(DossierRecipient::class, 'assigned_user_id');
    }

    /**
     * @return HasMany<DossierActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(DossierActivityLog::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role?->name === $roleName;
    }

    public function hasPermission(string $permissionName): bool
    {
        return (bool) $this->role?->hasPermission($permissionName);
    }

    public function initials(): string
    {
        return Str::upper(Str::substr((string) ($this->first_name ?: $this->name), 0, 1)
            .Str::substr((string) $this->last_name, 0, 1));
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
