<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'path' => 'attachments/'.fake()->uuid().'.jpg',
            'thumb_path' => null,
            'mime' => 'image/jpeg',
            'size' => fake()->numberBetween(1024, 5_000_000),
            'original_name' => fake()->word().'.jpg',
            'width' => 1920,
            'height' => 1080,
        ];
    }

    public function document(): static
    {
        return $this->state([
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'mime' => 'application/pdf',
            'original_name' => fake()->word().'.pdf',
            'width' => null,
            'height' => null,
        ]);
    }
}
