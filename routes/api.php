<?php

use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\ChannelJoinController;
use App\Http\Controllers\Api\ChannelMemberController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'excursion-api']));

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => UserResource::make($request->user()));

    // Канали (DELETE = архівація)
    Route::apiResource('channels', ChannelController::class);
    Route::post('channels/{channel}/join', ChannelJoinController::class)->name('channels.join');
    Route::apiResource('channels.members', ChannelMemberController::class)->only(['index', 'store', 'destroy']);

    // Повідомлення: index/store вкладені в канал, update/destroy — shallow
    Route::apiResource('channels.messages', MessageController::class)
        ->shallow()
        ->only(['index', 'store', 'update', 'destroy']);
});
