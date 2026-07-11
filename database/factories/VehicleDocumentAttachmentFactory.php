<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VehicleDocument;
use App\Models\VehicleDocumentAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleDocumentAttachment>
 */
class VehicleDocumentAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_document_id' => VehicleDocument::factory(),
            'user_id' => User::factory(),
            'disk' => 'local',
            'path' => 'auto/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'size' => fake()->numberBetween(20_000, 3_000_000),
        ];
    }
}
