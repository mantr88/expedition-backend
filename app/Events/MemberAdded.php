<?php

namespace App\Events;

use App\Http\Resources\ChannelMemberResource;
use App\Models\ChannelMember;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChannelMember $membership) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("channel.{$this->membership->channel_id}");
    }

    public function broadcastWith(): array
    {
        return ChannelMemberResource::make($this->membership->load('user'))->resolve();
    }
}
