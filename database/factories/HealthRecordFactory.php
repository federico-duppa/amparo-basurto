<?php

namespace Database\Factories;

use App\Enums\HealthSubjectType;
use App\Models\HealthRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthRecord>
 */
class HealthRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'titular' => fake()->name(),
            'titular_tipo' => HealthSubjectType::Persona,
            'nacimiento' => fake()->optional()->date(),
            'grupo_sanguineo' => fake()->optional()->randomElement(['0+', '0-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-']),
            'obra_social' => fake()->optional()->company(),
            'alergias' => fake()->optional()->sentence(),
            'condiciones' => fake()->optional()->sentence(),
            'medicacion' => fake()->optional()->sentence(),
        ];
    }

    public function mascota(): static
    {
        return $this->state([
            'titular' => fake()->firstName(),
            'titular_tipo' => HealthSubjectType::Mascota,
        ]);
    }

    public function documento(): static
    {
        return $this->state([
            'titular' => 'Ficha '.fake()->lastName(),
            'titular_tipo' => HealthSubjectType::Documento,
        ]);
    }
}
