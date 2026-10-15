<?php

namespace App\Models;

use App\Enums\RequestScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'response_delay_hours', 'scope', 'color', 'is_active', 'sort_order'])]
class UrgencyLevel extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_delay_hours' => 'integer',
            'scope' => RequestScope::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Dossier, $this>
     */
    public function dossiers(): HasMany
    {
        return $this->hasMany(Dossier::class);
    }

    public function dueDateFrom(CarbonInterface $receivedAt): CarbonInterface
    {
        return $receivedAt->copy()->addHours($this->response_delay_hours);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
