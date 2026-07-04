<?php

namespace Database\Factories;

use App\Models\Envelope;
use App\Models\EnvelopeMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnvelopeMovement>
 */
class EnvelopeMovementFactory extends Factory
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
            'envelope_id' => Envelope::factory(),
            'type' => EnvelopeMovement::APORTE,
            'amount' => fake()->numberBetween(5, 200) * 1000,
            'currency' => 'ARS',
            'moved_on' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'note' => null,
            'transfer_group' => null,
            'exchange_rate' => null,
        ];
    }

    public function retiro(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EnvelopeMovement::RETIRO,
        ]);
    }
}
