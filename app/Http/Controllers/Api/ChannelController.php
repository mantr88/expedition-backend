<?php

namespace App\Http\Controllers\Api;

use App\Actions\ArchiveChannel;
use App\Actions\CreateChannel;
use App\Actions\UpdateChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channel\StoreChannelRequest;
use App\Http\Requests\Channel\UpdateChannelRequest;
use App\Http\Resources\ChannelResource;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $channels = Channel::query()
            ->whereHas('members', fn ($query) => $query->where('user_id', $request->user()->id))
            ->withViewerContext($request->user())
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => ChannelResource::collection($channels),
        ]);
    }

    public function store(StoreChannelRequest $request, CreateChannel $createChannel): JsonResponse
    {
        $channel = $createChannel->handle($request->user(), $request->validated());

        return ChannelResource::make($channel->loadViewerContext($request->user()))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Channel $channel): ChannelResource
    {
        Gate::authorize('view', $channel);

        return ChannelResource::make($channel->loadViewerContext($request->user()));
    }

    public function update(UpdateChannelRequest $request, Channel $channel, UpdateChannel $updateChannel): ChannelResource
    {
        $updateChannel->handle($channel, $request->validated());

        return ChannelResource::make($channel->loadViewerContext($request->user()));
    }

    /**
     * DELETE = архівація (soft): канал і історія залишаються в БД.
     */
    public function destroy(Request $request, Channel $channel, ArchiveChannel $archiveChannel): ChannelResource
    {
        Gate::authorize('archive', $channel);

        $archiveChannel->handle($channel);

        return ChannelResource::make($channel->loadViewerContext($request->user()));
    }
}
