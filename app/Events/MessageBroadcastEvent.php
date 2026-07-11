<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Спільна база message-подій (фаза B2). Payload ідентичний REST
 * MessageResource — критично для дедуплікації на фронті. Ім'я події
 * для клієнта — коротке (".MessageSent"), без неймспейсу.
 */
abstract class MessageBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('channel.'.$this->message->channel_id);
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
        return MessageResource::make(
            $this->message->loadMissing(['user', 'attachments', 'reactions'])
                ->loadCount('replies')
                ->loadMax('replies', 'created_at')
        )->resolve();
    }
}
