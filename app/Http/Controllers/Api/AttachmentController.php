<?php

namespace App\Http\Controllers\Api;

use App\Actions\UploadAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attachment\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

class AttachmentController extends Controller
{
    public function store(
        StoreAttachmentRequest $request,
        Channel $channel,
        Message $message,
        UploadAttachment $uploadAttachment,
    ): JsonResponse {
        $attachment = $uploadAttachment->handle($message, $request->file('file'));

        return AttachmentResource::make($attachment)
            ->response()
            ->setStatusCode(201);
    }
}
