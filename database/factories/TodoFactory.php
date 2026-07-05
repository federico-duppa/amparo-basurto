<?php

namespace Database\Factories;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Todo>
 */
class TodoFactory extends Factory
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
            'title' => fake()->sentence(3),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }

    public function dueOn(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $date,
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'urgent' => true,
        ]);
    }

    public function important(): static
    {
        return $this->state(fn (array $attributes) => [
            'important' => true,
        ]);
    }

    public function repeats(string $interval): static
    {
        return $this->state(fn (array $attributes) => [
            'repeat_interval' => $interval,
        ]);
    }

    public function someday(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Todo::STATUS_SOMEDAY,
        ]);
    }

    public function waiting(?string $for = null): static
    {
        return $this->state(fn (array $attributes) => [
            'waiting' => true,
            'waiting_for' => $for,
        ]);
    }

    public function deferredUntil(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'deferred_until' => $date,
        ]);
    }
}
