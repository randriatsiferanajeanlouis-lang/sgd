<?php

namespace Database\Factories;

use App\Enums\RequestScope;
use App\Models\RequestType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RequestType>
 */
class RequestTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::ucfirst($name),
            'code' => Str::upper(Str::slug($name, '_')),
            'scope' => RequestScope::Both,
            'is_active' => true,
        ];
    }
}
