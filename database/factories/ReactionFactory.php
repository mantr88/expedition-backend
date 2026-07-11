<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reaction>
 */
class ReactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'user_id' => User::factory(),
            'emoji' => fake()->randomElement(['👍', '❤️', '😂', '🎉', '😮', '😢']),
        ];
    }
}
