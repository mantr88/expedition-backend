<?php

namespace App\Http\Controllers\Api;

use App\Actions\UpdateChannelNotifications;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channel\UpdateChannelNotificationsRequest;
use App\Http\Resources\ChannelResource;
use App\Models\Channel;

class ChannelNotificationsController extends Controller
{
    /**
     * Рівень сповіщень каналу для поточного користувача (фаза B5).
     * Відповідь — канал зі свіжим my_membership.
     */
    public function __invoke(UpdateChannelNotificationsRequest $request, Channel $channel, UpdateChannelNotifications $updateChannelNotifications): ChannelResource
    {
        $updateChannelNotifications->handle($request->user(), $channel, $request->validated('level'));

        return ChannelResource::make($channel->loadViewerContext($request->user()));
    }
}
