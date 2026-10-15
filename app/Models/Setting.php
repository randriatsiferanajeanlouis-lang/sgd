<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'type', 'group', 'label', 'description'])]
class Setting extends Model
{
    /**
     * Valeur typée d'un paramètre (selon sa colonne type), ou $default s'il n'existe pas.
     */
    public static function valueFor(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->firstWhere('key', $key);

        return $setting ? $setting->typedValue() : $default;
    }

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }
}
