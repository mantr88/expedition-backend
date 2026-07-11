<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\ListThreadRepliesRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ThreadReplyController extends Controller
{
    public function index(ListThreadRepliesRequest $request, Message $message): AnonymousResourceCollection
    {
        $limit = (int) ($request->validated('limit') ?? 50);
        $after = $request->validated('after');

        $replies = $message->replies()
            ->with(['user', 'attachments', 'reactions'])
            ->when($after !== null, fn ($query) => $query->where('id', '>', $after))
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $replies->count() > $limit;
        $page = $replies->take($limit);

        return MessageResource::collection($page)->additional([
            'meta' => [
                'has_more' => $hasMore,
                'next_cursor' => $hasMore ? $page->last()?->id : null,
            ],
        ]);
    }
}
