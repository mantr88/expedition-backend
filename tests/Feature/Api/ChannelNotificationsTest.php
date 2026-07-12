<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patchJson;

/*
 * Налаштування сповіщень каналу (фаза B5): notifications_level мого
 * членства — all | mentions | mute. Ідемпотентний PATCH.
 */

it('requires authentication', function () {
    $channel = makeChannelFor(User::factory()->create());

    patchJson("/api/channels/{$channel->id}/notifications", ['level' => 'mute'])
        ->assertUnauthorized();
});

it('forbids non-members', function () {
    $channel = makeChannelFor(User::factory()->create());

    actingAs(User::factory()->create());

    patchJson("/api/channels/{$channel->id}/notifications", ['level' => 'mute'])
        ->assertForbidden();
});

it('updates my notifications level and returns the channel', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    patchJson("/api/channels/{$channel->id}/notifications", ['level' => 'mentions'])
        ->assertOk()
        ->assertJsonPath('id', $channel->id)
        ->assertJsonPath('my_membership.notifications_level', 'mentions');

    expect($channel->membershipFor($user)->notifications_level)->toBe('mentions');
});

it('does not touch other members levels', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $channel = makeChannelFor($user);
    $channel->members()->create(['user_id' => $other->id, 'role' => 'member']);

    actingAs($user);

    patchJson("/api/channels/{$channel->id}/notifications", ['level' => 'mute'])->assertOk();

    expect($channel->membershipFor($other)->notifications_level)->toBe('all');
});

it('is idempotent for the same level', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    patchJson("/api/channels/{$channel->id}/notifications", ['level' => 'mute'])->assertOk();
    patchJson("/api/channels/{$channel->id}/notifications", ['level' => 'mute'])
        ->assertOk()
        ->assertJsonPath('my_membership.notifications_level', 'mute');
});

it('rejects an unknown level', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    patchJson("/api/channels/{$channel->id}/notifications", ['level' => 'loud'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('level');
});
