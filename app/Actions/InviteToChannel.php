<?php

namespace App\Actions;

use App\Events\AddedToChannel;
use App\Events\MemberAdded;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;

class InviteToChannel
{
    /**
     * Ідемпотентно: повторний інвайт наявного члена повертає існуюче
     * членство і повторної події AddedToChannel не генерує.
     */
    public function handle(Channel $channel, User $invitee): ChannelMember
    {
        $membership = $channel->members()->firstOrCreate(
            ['user_id' => $invitee->id],
            ['role' => 'member'],
        );

        if ($membership->wasRecentlyCreated) {
            AddedToChannel::dispatch($channel, $invitee);
            MemberAdded::dispatch($membership);
        }

        return $membership;
    }
}
