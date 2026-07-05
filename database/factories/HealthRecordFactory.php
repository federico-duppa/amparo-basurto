<?php

namespace Database\Factories;

use App\Models\HealthRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthRecord>
 */
class HealthRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tipo' => 'persona',
            'titular' => fake()->name(),
            'nacimiento' => fake()->optional()->date(),
            'grupo_sanguineo' => fake()->optional()->randomElement(['0+', '0-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-']),
            'obra_social' => fake()->optional()->company(),
            'alergias' => fake()->optional()->sentence(),
            'condiciones' => fake()->optional()->sentence(),
            'medicacion' => fake()->optional()->sentence(),
        ];
    }

    /**
     * El titular es una mascota: trae especie y raza, y la veterinaria ocupa el
     * lugar de la obra social.
     */
    public function mascota(): static
    {
        return $this->state(fn () => [
            'tipo' => 'mascota',
            'titular' => fake()->randomElement(['Firulais', 'Michi', 'Rocky', 'Luna', 'Toby', 'Nina']),
            'especie' => fake()->randomElement(['Perro', 'Gato']),
            'raza' => fake()->optional()->randomElement(['Mestizo', 'Labrador', 'Siamés', 'Caniche']),
            'grupo_sanguineo' => null,
        ]);
    }

    /**
     * La historia es un documento: una ficha neutra, sin datos de persona ni de
     * mascota.
     */
    public function documento(): static
    {
        return $this->state(fn () => [
            'tipo' => 'documento',
            'titular' => fake()->words(2, true),
            'nacimiento' => null,
            'grupo_sanguineo' => null,
            'obra_social' => null,
        ]);
    }
}
