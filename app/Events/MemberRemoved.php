<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberRemoved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $channel_id, public int $user_id) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("channel.{$this->channel_id}");
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'channel_id' => $this->channel_id,
            'user_id' => $this->user_id,
        ];
    }
}
