<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => null,
            'name' => fake()->unique()->slug(2),
            'type' => 'public',
            'topic' => fake()->sentence(4),
            'created_by' => User::factory(),
            'archived_at' => null,
        ];
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'private']);
    }

    public function dm(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'dm', 'topic' => null]);
    }
}
