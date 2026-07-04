<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'client_message_id' => (string) Str::uuid(),
            'body' => fake()->realTextBetween(20, 160),
            'type' => 'text',
            'edited_at' => null,
        ];
    }
}
