<?php

use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\ChannelJoinController;
use App\Http\Controllers\Api\ChannelMemberController;
use App\Http\Controllers\Api\ChannelNotificationsController;
use App\Http\Controllers\Api\ChannelReadController;
use App\Http\Controllers\Api\DirectMessageController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ReactionController;
use App\Http\Controllers\Api\SearchMessagesController;
use App\Http\Controllers\Api\ThreadReplyController;
use App\Http\Controllers\Api\UserController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'expedition-api']));

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => UserResource::make($request->user()));
    Route::get('/users', [UserController::class, 'index'])->name('users.index');

    // DM — той самий канал із type=dm; відкриття ідемпотентне
    Route::post('channels/dm', DirectMessageController::class)->name('direct-messages.open');
    Route::post('direct-messages', DirectMessageController::class);

    // Канали (DELETE = архівація)
    Route::apiResource('channels', ChannelController::class);
    Route::post('channels/{channel}/join', ChannelJoinController::class)->name('channels.join');
    Route::post('channels/{channel}/read', ChannelReadController::class)->name('channels.read');
    Route::patch('channels/{channel}/notifications', ChannelNotificationsController::class)->name('channels.notifications.update');
    Route::apiResource('channels.members', ChannelMemberController::class)->only(['index', 'store', 'destroy']);

    // Повідомлення: index/store вкладені в канал, update/destroy — shallow
    Route::apiResource('channels.messages', MessageController::class)
        ->shallow()
        ->only(['index', 'store', 'update', 'destroy']);

    // Вкладення: аплоад до повідомлення (B4)
    Route::post('channels/{channel}/messages/{message}/attachments', [AttachmentController::class, 'store'])
        ->name('messages.attachments.store');

    // Реакції: toggle (B4)
    Route::post('messages/{message}/reactions', [ReactionController::class, 'store'])
        ->name('messages.reactions.toggle');

    // Треди: список реплаїв (B4)
    Route::get('messages/{message}/replies', [ThreadReplyController::class, 'index'])
        ->name('messages.replies.index');

    // Пошук повідомлень (B5): full-text на Postgres, права — членство
    Route::get('search/messages', SearchMessagesController::class)->name('search.messages');
});
