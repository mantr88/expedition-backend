<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\SearchUsersRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /**
     * Пошук користувачів для DM/інвайтів (фаза B3): збіг за ім'ям або
     * email без урахування регістру; себе у видачі немає.
     */
    public function index(SearchUsersRequest $request): JsonResponse
    {
        $query = $request->validated('query');

        $users = User::query()
            ->whereKeyNot($request->user()->id)
            ->when(filled($query), fn ($builder) => $builder->where(
                fn ($builder) => $builder
                    ->whereLike('name', "%{$query}%", caseSensitive: false)
                    ->orWhereLike('email', "%{$query}%", caseSensitive: false),
            ))
            ->orderBy('name')
            ->limit(25)
            ->get();

        return response()->json([
            'data' => UserResource::collection($users),
        ]);
    }
}
