<?php

namespace Database\Factories;

use App\Models\HealthRecord;
use App\Models\HealthVaccine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthVaccine>
 */
class HealthVaccineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'health_record_id' => HealthRecord::factory(),
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Antigripal', 'Antitetánica', 'Hepatitis B', 'Fiebre amarilla']),
            'dose' => fake()->optional()->randomElement(['1ª dosis', '2ª dosis', 'Refuerzo', 'Única']),
            'applied_on' => fake()->dateTimeBetween('-5 years'),
            'next_due_on' => fake()->optional()->dateTimeBetween('now', '+2 years'),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
