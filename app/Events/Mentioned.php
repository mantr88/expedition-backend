<?php

namespace App\Events;

use App\Http\Resources\ChannelResource;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Персональна подія (фаза B3): користувача згадали через @. Летить на
 * private-user.{id} згаданого; payload { message, channel } — ті самі
 * REST-ресурси (контракт B3).
 */
class Mentioned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message, public User $user) {}

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
            'message' => MessageResource::make($this->message->loadMissing('user'))->resolve(),
            'channel' => ChannelResource::make($this->message->channel->toBroadcastPayloadFor($this->user))->resolve(),
        ];
    }
}
