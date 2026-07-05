<?php

namespace App\Http\Resources;

use App\Models\ChannelMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Учасник каналу для GET /api/channels/{channel}/members (фаза B1).
 *
 * @mixin ChannelMember
 */
class ChannelMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => UserResource::make($this->whenLoaded('user')),
            'role' => $this->role,
            'joined_at' => $this->created_at?->toISOString(),
        ];
    }
}
