<?php

namespace App\Http\Controllers\Api;

use App\Actions\JoinChannel;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChannelResource;
use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChannelJoinController extends Controller
{
    /**
     * Self-join у public-канал; private/dm — лише через інвайт.
     */
    public function __invoke(Request $request, Channel $channel, JoinChannel $joinChannel): ChannelResource
    {
        Gate::authorize('join', $channel);

        $joinChannel->handle($request->user(), $channel);

        return ChannelResource::make($channel->loadViewerContext($request->user()));
    }
}
