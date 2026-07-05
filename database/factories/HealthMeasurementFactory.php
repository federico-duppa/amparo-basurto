<?php

namespace Database\Factories;

use App\Models\HealthMeasurement;
use App\Models\HealthRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthMeasurement>
 */
class HealthMeasurementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'health_record_id' => HealthRecord::factory(),
            'user_id' => User::factory(),
            'type' => 'peso',
            'value' => fake()->randomFloat(1, 50, 110),
            'value2' => null,
            'measured_on' => fake()->dateTimeBetween('-1 year'),
        ];
    }

    public function presion(): static
    {
        return $this->state(fn () => [
            'type' => 'presion',
            'value' => fake()->numberBetween(100, 160),
            'value2' => fake()->numberBetween(60, 100),
        ]);
    }
}
