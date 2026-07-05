<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VehicleDocument;
use App\Models\VehicleDocumentRenewal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleDocumentRenewal>
 */
class VehicleDocumentRenewalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vehicle_document_id' => VehicleDocument::factory(),
            'expires_on' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
        ];
    }
}
