<?php

namespace App\Events;

use App\Http\Resources\ChannelResource;
use App\Models\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Оновлення каналу (назва/topic/архівація). Payload — REST ChannelResource;
 * per-viewer поле my_membership у broadcast завжди null (немає auth-контексту),
 * фронт зберігає власне членство з REST-даних.
 */
class ChannelUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Channel $channel) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('channel.'.$this->channel->id);
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
        // withoutRelations: контролер міг завантажити myMembership поточного
        // користувача — у спільному broadcast-payload воно має бути null.
        return ChannelResource::make($this->channel->withoutRelations()->loadCount('members'))->resolve();
    }
}
