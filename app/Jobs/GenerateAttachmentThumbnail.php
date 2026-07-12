<?php

namespace App\Jobs;

use App\Models\Attachment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Генерація thumbnail для зображень: зменшує до max 300px
 * по більшій стороні, зберігає поруч із оригіналом на local disk.
 */
class GenerateAttachmentThumbnail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30, 60];

    public function __construct(public Attachment $attachment) {}

    public function handle(): void
    {
        $disk = Storage::disk();
        $originalPath = $this->attachment->path;

        if (! $disk->exists($originalPath)) {
            return;
        }

        $manager = new ImageManager(new Driver);
        $image = $manager->read($disk->get($originalPath));

        $maxDim = 300;

        if ($image->width() <= $maxDim && $image->height() <= $maxDim) {
            $this->attachment->update(['thumb_path' => $originalPath]);

            return;
        }

        $image->scaleDown($maxDim, $maxDim);

        $extension = pathinfo($originalPath, PATHINFO_EXTENSION);
        $thumbPath = Str::replaceLast('.'.$extension, '_thumb.'.$extension, $originalPath);

        $encoded = match ($this->attachment->mime) {
            'image/png' => $image->toPng(),
            'image/gif' => $image->toGif(),
            'image/webp' => $image->toWebp(quality: 85),
            default => $image->toJpeg(quality: 85),
        };

        $disk->put($thumbPath, (string) $encoded);

        $this->attachment->update(['thumb_path' => $thumbPath]);
    }
}
