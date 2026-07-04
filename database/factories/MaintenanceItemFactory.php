<?php

namespace Database\Factories;

use App\Models\MaintenanceItem;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceItem>
 */
class MaintenanceItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vehicle_id' => Vehicle::factory(),
            'name' => fake()->randomElement(['Cambio de aceite', 'Cambio de bujías', 'Filtro de aire']),
            'interval_km' => fake()->randomElement([10000, 20000, 40000]),
            'interval_months' => fake()->randomElement([null, 12, 24]),
        ];
    }
}
