<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'email', 'manager_id', 'description', 'is_active', 'sort_order'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /**
     * Le service RH n'est pas un département : il fait partie de RSE.
     */
    public const HR_DEPARTMENT_CODE = 'RSE';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Dossiers internes émis par ce département.
     *
     * @return HasMany<Dossier, $this>
     */
    public function originatedDossiers(): HasMany
    {
        return $this->hasMany(Dossier::class, 'originating_department_id');
    }

    /**
     * @return HasMany<DossierRecipient, $this>
     */
    public function dossierRecipients(): HasMany
    {
        return $this->hasMany(DossierRecipient::class);
    }

    /**
     * Dossiers reçus par ce département, avec son statut propre en pivot.
     *
     * @return BelongsToMany<Dossier, $this>
     */
    public function receivedDossiers(): BelongsToMany
    {
        return $this->belongsToMany(Dossier::class, 'dossier_recipients')
            ->using(DossierRecipient::class)
            ->withPivot(['id', 'recipient_type', 'status', 'due_at', 'assigned_user_id'])
            ->withTimestamps();
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
