<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'envelope_id' => null,
            'description' => fake()->randomElement(['Supermercado', 'Café', 'Nafta', 'Farmacia', 'Verdulería', 'Cine']),
            'category' => fake()->randomElement(['Comida', 'Transporte', 'Salud', 'Salidas', 'Casa']),
            'amount' => fake()->numberBetween(1, 80) * 1000,
            'currency' => 'ARS',
            'spent_on' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'rate_ars' => null,
            'rate_source' => null,
            'reduces_target' => false,
        ];
    }

    public function enDolares(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'USD',
            'amount' => fake()->numberBetween(10, 300),
            'rate_ars' => fake()->numberBetween(900, 1500),
            'rate_source' => 'blue',
        ]);
    }
}
