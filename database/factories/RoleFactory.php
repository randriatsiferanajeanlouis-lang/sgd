<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->jobTitle();

        return [
            'name' => Str::slug($label, '_').'_'.fake()->unique()->randomNumber(5),
            'label' => $label,
            'is_system' => false,
        ];
    }
}
