<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Toggle реакції (фаза B4). Payload — компактна інформація про зміну,
 * фронт оновлює лічильник і reacted_by_me без рефетчу повідомлення.
 */
class ReactionToggled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public string $emoji,
        public int $count,
        public int $userId,
        public string $action,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('channel.' . $this->message->channel_id);
    }

    public function broadcastAs(): string
    {
        return 'ReactionToggled';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'emoji' => $this->emoji,
            'count' => $this->count,
            'user_id' => $this->userId,
            'action' => $this->action,
        ];
    }
}
