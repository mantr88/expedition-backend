<?php

namespace App\Actions;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;

class JoinChannel
{
    /**
     * Ідемпотентно: повторний join не створює дубль членства.
     */
    public function handle(User $user, Channel $channel): ChannelMember
    {
        return $channel->members()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => 'member'],
        );
    }
}
