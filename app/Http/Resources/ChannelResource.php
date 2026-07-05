<?php

namespace App\Http\Resources;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Контракт каналу (фази B1/B3). Потребує viewer-контексту в контролері
 * (Channel::withViewerContext / loadViewerContext) — без нього форма
 * буде неповною. У broadcast-payload per-viewer поля my_membership
 * та unread_count завжди null (фронт бере їх з REST).
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
            'name' => $this->displayName(),
            'type' => $this->type,
            'topic' => $this->topic,
            'archived_at' => $this->archived_at?->toISOString(),
            'members_count' => $this->members_count,
            'unread_count' => $this->whenCounted('unread_count', fn (): int => (int) $this->unread_count, null),
            'my_membership' => $this->whenLoaded('myMembership', fn (): array => [
                'role' => $this->myMembership->role,
                'last_read_message_id' => $this->myMembership->last_read_message_id,
                'notifications_level' => $this->myMembership->notifications_level,
            ], null),
        ];
    }

    /**
     * Ім'я DM-каналу — ім'я співрозмовника (контракт B3); технічне
     * `dm:{a}:{b}` з БД назовні не витікає лише за завантаженого резолвера.
     */
    private function displayName(): string
    {
        if ($this->type === 'dm' && $this->relationLoaded('dmCounterpart')) {
            return $this->dmCounterpart?->user->name ?? $this->name;
        }

        return $this->name;
    }
}
