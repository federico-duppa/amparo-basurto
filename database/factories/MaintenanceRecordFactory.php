<?php

namespace Database\Factories;

use App\Models\MaintenanceItem;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRecord>
 */
class MaintenanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vehicle_id' => Vehicle::factory(),
            'maintenance_item_id' => MaintenanceItem::factory(),
            'performed_on' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'mileage' => fake()->numberBetween(0, 200000),
            'cost' => fake()->randomElement([null, fake()->numberBetween(5000, 150000)]),
        ];
    }
}
