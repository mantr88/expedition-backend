<?php

namespace App\Events;

use App\Http\Resources\ChannelResource;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Персональна подія (фаза B3): користувача додали до каналу (інвайт
 * або відкритий із ним DM). Летить на private-user.{id} отримувача.
 */
class AddedToChannel implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Channel $channel, public User $user) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->user->id);
    }

    public function broadcastAs(): string
    {
        return class_basename($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'channel' => ChannelResource::make($this->channel->toBroadcastPayloadFor($this->user))->resolve(),
        ];
    }
}
