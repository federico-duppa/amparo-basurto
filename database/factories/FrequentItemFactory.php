<?php

namespace Database\Factories;

use App\Models\FrequentItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FrequentItem>
 */
class FrequentItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->randomElement(FrequentItem::DEFAULTS),
        ];
    }
}
