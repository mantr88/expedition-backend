<?php

use App\Http\Resources\UserResource;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Авторизація broadcast-каналів (фаза B2). Laravel відкидає префікси
 * private-/presence-, тож 'channel.{channel}' покриває водночас
 * private-channel.{id} і presence-channel.{id}: для private достатньо
 * truthy-значення, для presence повернутий масив стає інформацією про
 * учасника (UserResource — та сама форма, що й у REST).
 */

Broadcast::channel('channel.{channel}', function (User $user, Channel $channel): ?array {
    return $channel->isMember($user)
        ? UserResource::make($user)->resolve()
        : null;
});

Broadcast::channel('user.{id}', function (User $user, string $id): bool {
    return (int) $user->id === (int) $id;
});
