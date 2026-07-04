<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelMember>
 */
class ChannelMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => null,
            'channel_id' => Channel::factory(),
            'user_id' => User::factory(),
            'role' => 'member',
            'last_read_message_id' => null,
            'notifications_level' => 'all',
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'owner']);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'admin']);
    }
}
