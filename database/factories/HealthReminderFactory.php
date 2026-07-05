<?php

namespace Database\Factories;

use App\Models\HealthRecord;
use App\Models\HealthReminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthReminder>
 */
class HealthReminderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'health_record_id' => HealthRecord::factory(),
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Control clínico', 'Receta de la medicación', 'Estudio anual', 'Control con cardiología']),
            'expires_on' => fake()->dateTimeBetween('-1 month', '+1 year'),
            'interval_months' => fake()->optional()->randomElement([6, 12]),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
