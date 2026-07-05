<?php

namespace Database\Factories;

use App\Models\HealthContact;
use App\Models\HealthRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthContact>
 */
class HealthContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'health_record_id' => HealthRecord::factory(),
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'specialty' => fake()->optional()->randomElement(['Clínica médica', 'Cardiología', 'Dermatología', 'Pediatría']),
            'phone' => fake()->optional()->phoneNumber(),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
