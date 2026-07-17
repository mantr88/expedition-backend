<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

/*
 * Авторизація broadcast-каналів (фаза B2): private-channel.{id} — лише член,
 * private-user.{id} — лише сам користувач, presence-channel.{id} повертає
 * UserResource учасника. Тестове оточення шле broadcast у null-драйвер,
 * тому для перевірки підписів перемикаємось на reverb-конекшн і повторно
 * реєструємо channel-колбеки: під час boot вони були зареєстровані
 * на null-драйвері.
 */

beforeEach(function () {
    config(['broadcasting.default' => 'reverb']);

    require base_path('routes/channels.php');
});

it('authorizes a channel member on private-channel.{id}', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    postJson('/api/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => "private-channel.{$channel->id}",
    ])->assertSuccessful()->assertJsonStructure(['auth']);
});

it('rejects a non-member on private-channel.{id}', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $channel = makeChannelFor($member);

    actingAs($outsider);

    postJson('/api/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => "private-channel.{$channel->id}",
    ])->assertForbidden();
});

it('rejects an unauthenticated broadcasting auth request', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    postJson('/api/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => "private-channel.{$channel->id}",
    ])->assertUnauthorized();
});

it('authorizes only the user themselves on private-user.{id}', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    actingAs($user);

    postJson('/api/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => "private-user.{$user->id}",
    ])->assertSuccessful();

    postJson('/api/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => "private-user.{$other->id}",
    ])->assertForbidden();
});

it('returns the UserResource shape as presence member info on presence-channel.{id}', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    $response = postJson('/api/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => "presence-channel.{$channel->id}",
    ])->assertSuccessful()->assertJsonStructure(['auth', 'channel_data']);

    $channelData = json_decode($response->json('channel_data'), true);

    expect($channelData['user_id'])->toBe((string) $user->id)
        ->and($channelData['user_info'])->toEqual([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
            'last_seen_at' => $user->last_seen_at?->toISOString(),
            'is_pending' => false,
        ]);
});

it('rejects a non-member on presence-channel.{id}', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $channel = makeChannelFor($member);

    actingAs($outsider);

    postJson('/api/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => "presence-channel.{$channel->id}",
    ])->assertForbidden();
});
