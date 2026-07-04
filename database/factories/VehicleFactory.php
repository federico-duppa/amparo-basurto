<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'marca' => fake()->randomElement(['Volkswagen', 'Renault', 'Peugeot', 'Toyota', 'Fiat', 'Ford']),
            'modelo' => fake()->randomElement(['Gol', 'Clio', '208', 'Corolla', 'Cronos', 'Focus']),
            'patente' => strtoupper(fake()->bothify('??###??')),
            'kilometraje' => fake()->numberBetween(0, 200000),
        ];
    }
}
