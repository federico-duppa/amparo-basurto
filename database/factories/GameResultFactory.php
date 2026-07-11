<?php

namespace Database\Factories;

use App\Models\GameResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameResult>
 */
class GameResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game' => fake()->randomElement(['queens', 'solyluna']),
            'daily' => true,
            'played_on' => now()->toDateString(),
            'seconds' => fake()->numberBetween(30, 900),
        ];
    }
}
