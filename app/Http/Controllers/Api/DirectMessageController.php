<?php

namespace App\Http\Controllers\Api;

use App\Actions\OpenDirectMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\DirectMessage\OpenDirectMessageRequest;
use App\Http\Resources\ChannelResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DirectMessageController extends Controller
{
    /**
     * Відкрити (знайти або створити) DM зі співрозмовником:
     * 201 — новий канал, 200 — існуючий (ідемпотентно).
     */
    public function __invoke(OpenDirectMessageRequest $request, OpenDirectMessage $openDirectMessage): JsonResponse
    {
        $counterpart = User::findOrFail($request->validated('user_id'));

        $channel = $openDirectMessage->handle($request->user(), $counterpart);

        return ChannelResource::make($channel->loadViewerContext($request->user()))
            ->response()
            ->setStatusCode($channel->wasRecentlyCreated ? 201 : 200);
    }
}
