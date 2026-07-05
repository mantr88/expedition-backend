<?php

namespace App\Http\Controllers\Api;

use App\Actions\MarkChannelRead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channel\MarkChannelReadRequest;
use App\Http\Resources\ChannelResource;
use App\Models\Channel;

class ChannelReadController extends Controller
{
    /**
     * Read-маркер каналу: ідемпотентний і монотонний (last_read не
     * зменшується). Відповідь — канал зі свіжим unread_count.
     */
    public function __invoke(MarkChannelReadRequest $request, Channel $channel, MarkChannelRead $markChannelRead): ChannelResource
    {
        $markChannelRead->handle($request->user(), $channel, (int) $request->validated('last_read_message_id'));

        return ChannelResource::make($channel->loadViewerContext($request->user()));
    }
}
