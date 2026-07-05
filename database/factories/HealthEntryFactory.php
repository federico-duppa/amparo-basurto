<?php

namespace Database\Factories;

use App\Models\HealthEntry;
use App\Models\HealthRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthEntry>
 */
class HealthEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'health_record_id' => HealthRecord::factory(),
            'user_id' => User::factory(),
            'occurred_on' => fake()->dateTimeBetween('-2 years'),
            'type' => fake()->randomElement(array_keys(HealthEntry::TYPES)),
            'title' => fake()->sentence(4),
            'detail' => fake()->optional()->paragraph(),
        ];
    }
}
