<?php

namespace Database\Factories;

use App\Models\Envelope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Envelope>
 */
class EnvelopeFactory extends Factory
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
            'name' => fake()->randomElement(['Vacaciones', 'Auto nuevo', 'Seguro', 'Service', 'Fondo de emergencia', 'Regalos']),
            'kind' => Envelope::KIND_AHORRO,
            'currency' => 'ARS',
            'indexed' => false,
            'target_amount' => fake()->randomElement([null, fake()->numberBetween(50, 900) * 1000]),
            'target_month' => null,
        ];
    }

    public function gasto(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => Envelope::KIND_GASTO,
            'indexed' => false,
        ]);
    }

    public function enDolares(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'USD',
            'indexed' => false,
        ]);
    }

    public function indexado(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => Envelope::KIND_AHORRO,
            'currency' => 'ARS',
            'indexed' => true,
            'target_amount' => fake()->numberBetween(100, 900) * 1000,
            'target_month' => now()->startOfMonth(),
        ]);
    }
}
