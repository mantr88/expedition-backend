<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;

/**
 * Результат пошуку (контракт B5): MessageResource + контекст каналу,
 * щоб фронт показав джерело збігу без додаткових запитів. Канал — без
 * per-viewer полів (my_membership/unread_count = null): фронт бере їх
 * зі свого списку каналів.
 *
 * @mixin Message
 */
class SearchResultResource extends MessageResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'channel' => ChannelResource::make($this->whenLoaded('channel')),
        ]);
    }
}
