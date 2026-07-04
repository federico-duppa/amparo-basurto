<?php

namespace Database\Factories;

use App\Models\FuelLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelLog>
 */
class FuelLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vehicle_id' => Vehicle::factory(),
            'filled_on' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'mileage' => fake()->numberBetween(0, 200000),
            'cost' => fake()->numberBetween(5000, 60000),
        ];
    }
}
