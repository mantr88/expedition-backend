<?php

namespace App\Http\Controllers\Api;

use App\Actions\SearchMessages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchMessagesRequest;
use App\Http\Resources\SearchResultResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SearchMessagesController extends Controller
{
    /**
     * GET /api/search/messages?q=&channel_id=&before=&limit= (фаза B5).
     * Права — лише канали користувача; пагінація курсорна, як у стрічці.
     */
    public function __invoke(SearchMessagesRequest $request, SearchMessages $searchMessages): AnonymousResourceCollection
    {
        $result = $searchMessages->handle(
            $request->user(),
            $request->validated('q'),
            $request->filled('channel_id') ? (int) $request->validated('channel_id') : null,
            $request->filled('before') ? (int) $request->validated('before') : null,
            (int) ($request->validated('limit') ?? 50),
        );

        return SearchResultResource::collection($result['messages'])->additional([
            'meta' => [
                'has_more' => $result['has_more'],
                'next_cursor' => $result['has_more'] ? $result['messages']->last()?->id : null,
            ],
        ]);
    }
}
