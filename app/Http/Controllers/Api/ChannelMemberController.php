<?php

namespace App\Http\Controllers\Api;

use App\Actions\InviteToChannel;
use App\Actions\LeaveChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channel\InviteChannelMemberRequest;
use App\Http\Resources\ChannelMemberResource;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ChannelMemberController extends Controller
{
    public function index(Channel $channel): JsonResponse
    {
        Gate::authorize('view', $channel);

        $members = $channel->members()->with('user')->orderBy('id')->get();

        return response()->json([
            'data' => ChannelMemberResource::collection($members),
        ]);
    }

    public function store(InviteChannelMemberRequest $request, Channel $channel, InviteToChannel $inviteToChannel): JsonResponse
    {
        $invitee = User::findOrFail($request->validated('user_id'));

        $membership = $inviteToChannel->handle($channel, $invitee);

        return ChannelMemberResource::make($membership->load('user'))
            ->response()
            ->setStatusCode($membership->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Self-leave і kick — один ендпоінт; різниця прав у ChannelPolicy::removeMember.
     */
    public function destroy(Channel $channel, User $member, LeaveChannel $leaveChannel): Response
    {
        Gate::authorize('removeMember', [$channel, $member]);

        $leaveChannel->handle($channel, $member);

        return response()->noContent();
    }
}
