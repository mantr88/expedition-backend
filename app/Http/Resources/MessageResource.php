<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

/**
 * Контракт повідомлення (фаза B4). reactions/attachments заповнені,
 * reply_count/last_reply_at додані для тредів.
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
            'reactions' => $this->aggregateReactions(),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'reply_count' => $this->whenCounted('replies', fn (): int => (int) $this->replies_count, 0),
            'last_reply_at' => $this->whenAggregated('replies', 'created_at', 'max', fn (): ?string => $this->replies_max_created_at ? \Carbon\Carbon::parse($this->replies_max_created_at)->toISOString() : null, null),
        ];
    }

    /**
     * Агрегація реакцій: [{ emoji, count, reacted_by_me }] — per-viewer.
     *
     * @return list<array{emoji: string, count: int, reacted_by_me: bool}>
     */
    private function aggregateReactions(): array
    {
        if (! $this->relationLoaded('reactions')) {
            return [];
        }

        $userId = Auth::id();

        return $this->reactions
            ->groupBy('emoji')
            ->map(fn ($group, string $emoji): array => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'reacted_by_me' => $group->contains('user_id', $userId),
            ])
            ->values()
            ->all();
    }
}
