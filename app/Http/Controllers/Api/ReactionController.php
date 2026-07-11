<?php

namespace App\Http\Controllers\Api;

use App\Actions\ToggleReaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reaction\ToggleReactionRequest;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

class ReactionController extends Controller
{
    public function store(
        ToggleReactionRequest $request,
        Message $message,
        ToggleReaction $toggleReaction,
    ): JsonResponse {
        $result = $toggleReaction->handle($request->user(), $message, $request->validated('emoji'));

        return response()->json($result);
    }
}
