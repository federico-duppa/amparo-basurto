<?php

namespace Database\Factories;

use App\Models\ShoppingItem;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShoppingItem>
 */
class ShoppingItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shopping_list_id' => ShoppingList::factory(),
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Leche', 'Pan', 'Huevos', 'Manteca', 'Tomate', 'Cebolla', 'Aceite']),
        ];
    }
}
