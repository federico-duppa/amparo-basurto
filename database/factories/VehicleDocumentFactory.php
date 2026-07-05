<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleDocument>
 */
class VehicleDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vehicle_id' => Vehicle::factory(),
            'name' => fake()->randomElement(['Seguro', 'VTV', 'Patente']),
            'expires_on' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'note' => null,
        ];
    }
}
