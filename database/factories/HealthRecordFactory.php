<?php

namespace Database\Factories;

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
            'nacimiento' => fake()->optional()->date(),
            'grupo_sanguineo' => fake()->optional()->randomElement(['0+', '0-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-']),
            'obra_social' => fake()->optional()->company(),
            'alergias' => fake()->optional()->sentence(),
            'condiciones' => fake()->optional()->sentence(),
            'medicacion' => fake()->optional()->sentence(),
        ];
    }
}
