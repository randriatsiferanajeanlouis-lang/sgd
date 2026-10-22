<?php

namespace App\Models;

use App\Enums\RequestScope;
use Database\Factories\RequestTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'description', 'scope', 'requires_complete_form', 'is_active', 'sort_order'])]
class RequestType extends Model
{
    /** @use HasFactory<RequestTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => RequestScope::class,
            'requires_complete_form' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Identifiants des types dont le formulaire doit être rempli entièrement.
     *
     * @return list<int>
     */
    public static function completeFormIds(): array
    {
        return static::query()->where('requires_complete_form', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public static function requiresCompleteForm(mixed $id): bool
    {
        return filled($id) && static::query()->whereKey($id)->where('requires_complete_form', true)->exists();
    }

    /** Type « Autre » : l'expéditeur précise lui-même le type de sa demande. */
    public const OTHER_CODE = 'AUTRE';

    public static function otherId(): ?int
    {
        $id = static::query()->where('code', self::OTHER_CODE)->value('id');

        return $id === null ? null : (int) $id;
    }

    public static function isOther(mixed $id): bool
    {
        return filled($id) && (int) $id === static::otherId();
    }

    /**
     * @return HasMany<Dossier, $this>
     */
    public function dossiers(): HasMany
    {
        return $this->hasMany(Dossier::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Types utilisables pour la portée donnée (y compris ceux valables pour les deux).
     */
    #[Scope]
    protected function availableFor(Builder $query, RequestScope $scope): Builder
    {
        return $query->whereIn('scope', [$scope->value, RequestScope::Both->value]);
    }
}
