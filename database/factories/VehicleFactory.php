<?php

namespace Database\Factories;

use App\Enums\VehicleType;
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
            'tipo' => VehicleType::Auto,
            'marca' => fake()->randomElement(['Volkswagen', 'Renault', 'Peugeot', 'Toyota', 'Fiat', 'Ford']),
            'modelo' => fake()->randomElement(['Gol', 'Clio', '208', 'Corolla', 'Cronos', 'Focus']),
            'patente' => strtoupper(fake()->bothify('??###??')),
            'kilometraje' => fake()->numberBetween(0, 200000),
        ];
    }

    public function moto(): static
    {
        return $this->state([
            'tipo' => VehicleType::Moto,
            'marca' => fake()->randomElement(['Honda', 'Yamaha', 'Zanella', 'Motomel']),
            'modelo' => fake()->randomElement(['Wave', 'FZ', 'ZB 110', 'Skua 150']),
        ]);
    }
}
