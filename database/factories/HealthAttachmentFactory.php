<?php

namespace Database\Factories;

use App\Models\HealthAttachment;
use App\Models\HealthRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthAttachment>
 */
class HealthAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'health_record_id' => HealthRecord::factory(),
            'health_entry_id' => null,
            'user_id' => User::factory(),
            'disk' => 'local',
            'path' => 'salud/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'size' => fake()->numberBetween(20_000, 3_000_000),
        ];
    }
}
