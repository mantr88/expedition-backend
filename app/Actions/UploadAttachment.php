<?php

namespace App\Actions;

use App\Jobs\GenerateAttachmentThumbnail;
use App\Models\Attachment;
use App\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class UploadAttachment
{
    /**
     * Зберігає файл на local disk у підкаталозі attachments/channels/{id}/,
     * записує метадані, для зображень диспатчить thumbnail-job у чергу.
     */
    public function handle(Message $message, UploadedFile $file): Attachment
    {
        $path = $file->storeAs(
            "attachments/channels/{$message->channel_id}",
            Str::uuid().'.'.$file->getClientOriginalExtension(),
        );

        $width = null;
        $height = null;

        if (str_starts_with($file->getMimeType(), 'image/')) {
            $dimensions = getimagesize($file->getRealPath());
            if ($dimensions !== false) {
                [$width, $height] = $dimensions;
            }
        }

        $attachment = $message->attachments()->create([
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
            'width' => $width,
            'height' => $height,
        ]);

        if (str_starts_with($attachment->mime, 'image/')) {
            GenerateAttachmentThumbnail::dispatch($attachment);
        }

        return $attachment;
    }
}
