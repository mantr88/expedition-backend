<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Контракт повідомлення (фаза B1). reactions/attachments — порожні масиви,
 * заповняться у B4; форма закладена одразу, щоб не міняти контракт із фронтом.
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_message_id' => $this->client_message_id,
            'channel_id' => $this->channel_id,
            'user' => UserResource::make($this->whenLoaded('user')),
            'parent_id' => $this->parent_id,
            'body_html' => $this->body_html,
            'body_raw' => $this->body,
            'type' => $this->type,
            'edited_at' => $this->edited_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'reactions' => [],
            'attachments' => [],
        ];
    }
}
