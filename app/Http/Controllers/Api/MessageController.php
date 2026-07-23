<?php

namespace App\Http\Controllers\Api;

use App\Actions\DeleteMessage;
use App\Actions\EditMessage;
use App\Actions\SendMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Message\ListMessagesRequest;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Http\Requests\Message\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    /**
     * Курсорна пагінація вгору (старіші): ?before={id}&limit=50.
     * Порядок — id DESC (новіші першими), next_cursor = id найстарішого
     * у сторінці, has_more — чи є ще старіші. Реплаї тредів у стрічку
     * не входять (контракт B4).
     */
    public function index(ListMessagesRequest $request, Channel $channel): AnonymousResourceCollection
    {
        $limit = (int) ($request->validated('limit') ?? 50);
        $before = $request->validated('before');

        $messages = $channel->messages()
            ->whereNull('parent_id')
            ->with(['user', 'attachments', 'reactions.user'])
            ->withCount('replies')
            ->withMax('replies', 'created_at')
            ->when($before !== null, fn ($query) => $query->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $messages->count() > $limit;
        $page = $messages->take($limit);

        return MessageResource::collection($page)->additional([
            'meta' => [
                'has_more' => $hasMore,
                'next_cursor' => $hasMore ? $page->last()?->id : null,
            ],
        ]);
    }

    public function store(StoreMessageRequest $request, Channel $channel, SendMessage $sendMessage): JsonResponse
    {
        $message = $sendMessage->handle($request->user(), $channel, $request->validated());

        return MessageResource::make(
            $message->load(['user', 'attachments', 'reactions.user'])
                ->loadCount('replies')
                ->loadMax('replies', 'created_at')
        )
            ->response()
            ->setStatusCode($message->wasRecentlyCreated ? 201 : 200);
    }

    public function update(UpdateMessageRequest $request, Message $message, EditMessage $editMessage): MessageResource
    {
        $editMessage->handle($message, $request->validated('body'));

        return MessageResource::make(
            $message->load(['user', 'attachments', 'reactions.user'])
                ->loadCount('replies')
                ->loadMax('replies', 'created_at')
        );
    }

    public function destroy(Message $message, DeleteMessage $deleteMessage): Response
    {
        Gate::authorize('delete', $message);

        $deleteMessage->handle($message);

        return response()->noContent();
    }
}
