<?php

namespace App\Actions;

use App\Events\AddedToChannel;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OpenDirectMessage
{
    /**
     * Знаходить або створює DM-канал двох користувачів (ідемпотентно):
     * технічне ім'я dm:{minId}:{maxId} детерміноване, тож повторне
     * відкриття повертає той самий канал. Назовні ім'я резолвиться
     * в ім'я співрозмовника (ChannelResource).
     */
    public function handle(User $initiator, User $counterpart): Channel
    {
        $name = sprintf('dm:%d:%d', min($initiator->id, $counterpart->id), max($initiator->id, $counterpart->id));

        $existing = Channel::query()->where('type', 'dm')->where('name', $name)->first();

        if ($existing !== null) {
            return $existing;
        }

        $channel = DB::transaction(function () use ($initiator, $counterpart, $name): Channel {
            $channel = Channel::create([
                'name' => $name,
                'type' => 'dm',
                'created_by' => $initiator->id,
            ]);

            $channel->members()->create(['user_id' => $initiator->id, 'role' => 'member']);
            $channel->members()->create(['user_id' => $counterpart->id, 'role' => 'member']);

            return $channel;
        });

        // Співрозмовник дізнається про новий DM без refetch списку каналів.
        AddedToChannel::dispatch($channel, $counterpart);

        return $channel;
    }
}
