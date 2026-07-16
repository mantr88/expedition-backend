<?php

namespace App\Actions;

use App\Events\MemberAdded;
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
        $membership = $channel->members()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => 'member'],
        );

        if ($membership->wasRecentlyCreated) {
            MemberAdded::dispatch($membership);
        }

        return $membership;
    }
}
