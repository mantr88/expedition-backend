<?php

namespace App\Actions;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateChannel
{
    /**
     * Створює канал і додає творця як owner — атомарно.
     *
     * @param  array{name: string, type: string, topic?: string|null}  $attributes
     */
    public function handle(User $creator, array $attributes): Channel
    {
        return DB::transaction(function () use ($creator, $attributes): Channel {
            $channel = Channel::create([
                'name' => $attributes['name'],
                'type' => $attributes['type'],
                'topic' => $attributes['topic'] ?? null,
                'created_by' => $creator->id,
            ]);

            $channel->members()->create([
                'user_id' => $creator->id,
                'role' => 'owner',
            ]);

            return $channel;
        });
    }
}
