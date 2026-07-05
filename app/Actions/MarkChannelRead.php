<?php

namespace App\Actions;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;

class MarkChannelRead
{
    /**
     * Монотонний read-маркер: last_read_message_id ніколи не зменшується,
     * повтор із тим самим значенням — no-op (ідемпотентність).
     */
    public function handle(User $user, Channel $channel, int $lastReadMessageId): ChannelMember
    {
        $membership = $channel->members()->where('user_id', $user->id)->firstOrFail();

        if ($membership->last_read_message_id === null || $lastReadMessageId > $membership->last_read_message_id) {
            $membership->update(['last_read_message_id' => $lastReadMessageId]);
        }

        return $membership;
    }
}
