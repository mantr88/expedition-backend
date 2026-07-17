<?php

namespace App\Http\Controllers\Api;

use App\Actions\InviteUserByEmail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreInvitationRequest;
use App\Http\Resources\UserResource;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;

class InvitationController extends Controller
{
    public function store(StoreInvitationRequest $request, InviteUserByEmail $inviteAction): JsonResponse
    {
        $channel = $request->filled('channel_id')
            ? Channel::find($request->validated('channel_id'))
            : null;

        $user = $inviteAction->handle($request->validated('email'), $channel);

        return UserResource::make($user)
            ->response()
            ->setStatusCode($user->wasRecentlyCreated ? 201 : 200);
    }
}
