<?php

namespace App\Actions;

use App\Models\Channel;
use App\Models\User;

class LeaveChannel
{
    /**
     * Видаляє членство користувача — і для self-leave, і для kick
     * (авторизація різниться на рівні ChannelPolicy::removeMember).
     */
    public function handle(Channel $channel, User $member): void
    {
        $channel->members()->where('user_id', $member->id)->delete();
    }
}
