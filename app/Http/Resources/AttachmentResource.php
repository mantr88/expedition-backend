<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Контракт вкладення (фаза B4). URL — signed temporary URL
 * через local disk serve:true (VPS) або S3 temporaryUrl (prod).
 *
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => Storage::temporaryUrl($this->path, now()->addHour()),
            'thumb_url' => $this->thumb_path
                ? Storage::temporaryUrl($this->thumb_path, now()->addHour())
                : null,
            'mime' => $this->mime,
            'size' => $this->size,
            'original_name' => $this->original_name,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
