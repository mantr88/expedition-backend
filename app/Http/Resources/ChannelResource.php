<?php

namespace App\Http\Resources;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Контракт каналу (фаза B1). Потребує withCount('members') та
 * with('myMembership') у контролері — без них форма буде неповною.
 *
 * @mixin Channel
 */
class ChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'topic' => $this->topic,
            'archived_at' => $this->archived_at?->toISOString(),
            'members_count' => $this->members_count,
            'my_membership' => $this->whenLoaded('myMembership', fn (): array => [
                'role' => $this->myMembership->role,
                'last_read_message_id' => $this->myMembership->last_read_message_id,
                'notifications_level' => $this->myMembership->notifications_level,
            ], null),
        ];
    }
}
