<?php

namespace App\Actions;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;

class InviteToChannel
{
    /**
     * Ідемпотентно: повторний інвайт наявного члена повертає існуюче членство.
     */
    public function handle(Channel $channel, User $invitee): ChannelMember
    {
        return $channel->members()->firstOrCreate(
            ['user_id' => $invitee->id],
            ['role' => 'member'],
        );
    }
}
