<?php

namespace Database\Factories;

use App\Enums\DossierOrigin;
use App\Enums\DossierStatus;
use App\Enums\ReceptionChannel;
use App\Models\Department;
use App\Models\Dossier;
use App\Models\ExternalRequester;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Par défaut : dossier interne émis par un département.
 *
 * @extends Factory<Dossier>
 */
class DossierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'DOS-'.Str::upper(Str::random(10)),
            'origin' => DossierOrigin::Internal,
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'request_type_id' => RequestType::factory(),
            'originating_department_id' => Department::factory(),
            'created_by' => User::factory(),
            'received_at' => now(),
            'status' => DossierStatus::Sent,
        ];
    }

    public function external(): static
    {
        return $this->state(fn (array $attributes) => [
            'origin' => DossierOrigin::External,
            'originating_department_id' => null,
            'external_requester_id' => ExternalRequester::factory(),
            'reception_channel' => ReceptionChannel::Email,
        ]);
    }
}
