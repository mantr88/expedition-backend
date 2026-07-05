<?php

namespace Database\Factories;

use App\Models\Mention;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mention>
 */
class MentionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'mentioned_user_id' => User::factory(),
        ];
    }
}
