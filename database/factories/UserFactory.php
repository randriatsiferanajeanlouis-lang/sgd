<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'name' => "{$firstName} {$lastName}",
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => Str::lower(fake()->unique()->userName()),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'department_id' => Department::factory(),
            'role_id' => Role::factory(),
            'job_title' => fake()->jobTitle(),
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withoutEmail(): static
    {
        return $this->state(fn () => ['email' => null, 'email_verified_at' => null]);
    }

    /**
     * Rattache l'utilisateur à un rôle et un département issus des seeders.
     */
    public function withRole(string $roleName, string $departmentCode = 'PRODUCTION'): static
    {
        return $this->state(fn () => [
            'role_id' => Role::query()->where('name', $roleName)->value('id'),
            'department_id' => $roleName === Role::ADMIN
                ? null
                : Department::query()->where('code', $departmentCode)->value('id'),
        ]);
    }
}
