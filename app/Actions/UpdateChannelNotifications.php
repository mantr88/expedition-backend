<?php

namespace App\Actions;

use App\Models\Channel;
use App\Models\User;

class UpdateChannelNotifications
{
    /**
     * Рівень сповіщень мого членства (фаза B5): all | mentions | mute.
     * Ідемпотентно: повторне встановлення того самого рівня — no-op.
     */
    public function handle(User $user, Channel $channel, string $level): void
    {
        $channel->members()
            ->where('user_id', $user->id)
            ->update(['notifications_level' => $level]);
    }
}
