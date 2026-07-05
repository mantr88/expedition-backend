<?php

use App\Models\Channel;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

/*
 * CRUD каналів (фаза B1): виконуваний контракт ChannelResource,
 * авторизація за членством і роллю.
 */

it('requires authentication', function () {
    getJson('/api/channels')->assertUnauthorized();
});

it('lists only channels the user is a member of', function () {
    $user = User::factory()->create();
    $mine = makeChannelFor($user);
    Channel::factory()->create(); // чужий канал

    actingAs($user);

    getJson('/api/channels')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

it('serializes the exact ChannelResource contract shape', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user, role: 'owner');
    $channel->update(['name' => 'general', 'topic' => 'Головний канал']);

    actingAs($user);

    getJson("/api/channels/{$channel->id}")->assertOk()->assertExactJson([
        'id' => $channel->id,
        'name' => 'general',
        'type' => 'public',
        'topic' => 'Головний канал',
        'archived_at' => null,
        'members_count' => 1,
        'my_membership' => [
            'role' => 'owner',
            'last_read_message_id' => null,
            'notifications_level' => 'all',
        ],
    ]);
});

it('creates a channel and makes the creator its owner', function () {
    $user = User::factory()->create();

    actingAs($user);

    $response = postJson('/api/channels', [
        'name' => 'random',
        'type' => 'private',
        'topic' => 'Флуд',
    ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'random')
        ->assertJsonPath('type', 'private')
        ->assertJsonPath('members_count', 1)
        ->assertJsonPath('my_membership.role', 'owner');

    $channel = Channel::findOrFail($response->json('id'));
    expect($channel->created_by)->toBe($user->id)
        ->and($channel->membershipFor($user)?->role)->toBe('owner');
});

it('rejects invalid channel payloads', function (array $payload) {
    actingAs(User::factory()->create());

    postJson('/api/channels', $payload)->assertUnprocessable();
})->with([
    'without name' => [['type' => 'public']],
    'dm type is not creatable directly' => [['name' => 'x', 'type' => 'dm']],
    'unknown type' => [['name' => 'x', 'type' => 'secret']],
    'name too long' => [['name' => str_repeat('a', 81), 'type' => 'public']],
]);

it('returns 403 for a non-member on show', function () {
    $channel = Channel::factory()->create();

    actingAs(User::factory()->create());

    getJson("/api/channels/{$channel->id}")->assertForbidden();
});

it('updates name and topic as an admin', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user, role: 'admin');

    actingAs($user);

    patchJson("/api/channels/{$channel->id}", ['name' => 'renamed', 'topic' => null])
        ->assertOk()
        ->assertJsonPath('name', 'renamed')
        ->assertJsonPath('topic', null);
});

it('forbids update for a regular member', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    patchJson("/api/channels/{$channel->id}", ['name' => 'renamed'])->assertForbidden();
});

it('archives a channel as the owner', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user, role: 'owner');

    actingAs($user);

    deleteJson("/api/channels/{$channel->id}")
        ->assertOk()
        ->assertJsonPath('id', $channel->id);

    expect($channel->refresh()->archived_at)->not->toBeNull();
});

it('forbids archiving for a regular member', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    deleteJson("/api/channels/{$channel->id}")->assertForbidden();

    expect($channel->refresh()->archived_at)->toBeNull();
});
