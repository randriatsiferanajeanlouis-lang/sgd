<?php

namespace Database\Factories;

use App\Enums\RequesterType;
use App\Models\ExternalRequester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalRequester>
 */
class ExternalRequesterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => RequesterType::Person,
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
        ];
    }

    public function organization(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RequesterType::Organization,
            'organization' => fake()->company(),
        ]);
    }
}
