<?php

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * Членство в каналах (фаза B1): список, інвайт, kick, self-leave, self-join.
 */

it('lists channel members with their users', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user, role: 'owner');
    $other = User::factory()->create();
    ChannelMember::factory()->for($channel)->for($other)->create();

    actingAs($user);

    getJson("/api/channels/{$channel->id}/members")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.role', 'owner')
        ->assertJsonPath('data.0.user.id', $user->id);
});

it('forbids the member list for non-members', function () {
    $channel = Channel::factory()->create();

    actingAs(User::factory()->create());

    getJson("/api/channels/{$channel->id}/members")->assertForbidden();
});

it('lets any member invite to a public channel', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $invitee = User::factory()->create();

    actingAs($user);

    postJson("/api/channels/{$channel->id}/members", ['user_id' => $invitee->id])
        ->assertCreated()
        ->assertJsonPath('user.id', $invitee->id)
        ->assertJsonPath('role', 'member');

    expect($channel->isMember($invitee))->toBeTrue();
});

it('does not duplicate membership on repeated invite', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $invitee = User::factory()->create();

    actingAs($user);

    postJson("/api/channels/{$channel->id}/members", ['user_id' => $invitee->id])->assertCreated();
    postJson("/api/channels/{$channel->id}/members", ['user_id' => $invitee->id])->assertOk();

    expect($channel->members()->where('user_id', $invitee->id)->count())->toBe(1);
});

it('forbids a regular member to invite into a private channel', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user, type: 'private');

    actingAs($user);

    postJson("/api/channels/{$channel->id}/members", ['user_id' => User::factory()->create()->id])
        ->assertForbidden();
});

it('lets the owner invite into a private channel', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user, role: 'owner', type: 'private');

    actingAs($user);

    postJson("/api/channels/{$channel->id}/members", ['user_id' => User::factory()->create()->id])
        ->assertCreated();
});

it('forbids invites into a dm channel', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user, type: 'dm');

    actingAs($user);

    postJson("/api/channels/{$channel->id}/members", ['user_id' => User::factory()->create()->id])
        ->assertForbidden();
});

it('validates the invited user id', function (array $payload) {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    postJson("/api/channels/{$channel->id}/members", $payload)->assertUnprocessable();
})->with([
    'missing user_id' => [[]],
    'unknown user' => [['user_id' => 999999]],
]);

it('lets an admin kick a regular member', function () {
    $admin = User::factory()->create();
    $channel = makeChannelFor($admin, role: 'admin');
    $member = User::factory()->create();
    ChannelMember::factory()->for($channel)->for($member)->create();

    actingAs($admin);

    deleteJson("/api/channels/{$channel->id}/members/{$member->id}")->assertNoContent();

    expect($channel->isMember($member))->toBeFalse();
});

it('forbids kicking the channel owner', function () {
    $admin = User::factory()->create();
    $channel = makeChannelFor($admin, role: 'admin');
    $owner = User::factory()->create();
    ChannelMember::factory()->for($channel)->for($owner)->owner()->create();

    actingAs($admin);

    deleteJson("/api/channels/{$channel->id}/members/{$owner->id}")->assertForbidden();
});

it('forbids a regular member to kick others', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $other = User::factory()->create();
    ChannelMember::factory()->for($channel)->for($other)->create();

    actingAs($user);

    deleteJson("/api/channels/{$channel->id}/members/{$other->id}")->assertForbidden();
});

it('lets a member leave the channel', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    deleteJson("/api/channels/{$channel->id}/members/{$user->id}")->assertNoContent();

    expect($channel->isMember($user))->toBeFalse();
});

it('lets a user join a public channel', function () {
    $user = User::factory()->create();
    $channel = Channel::factory()->create();

    actingAs($user);

    postJson("/api/channels/{$channel->id}/join")
        ->assertOk()
        ->assertJsonPath('my_membership.role', 'member');

    expect($channel->isMember($user))->toBeTrue();
});

it('forbids joining a private channel without an invite', function () {
    $channel = Channel::factory()->private()->create();

    actingAs(User::factory()->create());

    postJson("/api/channels/{$channel->id}/join")->assertForbidden();
});

it('forbids joining an archived channel', function () {
    $channel = Channel::factory()->create(['archived_at' => now()]);

    actingAs(User::factory()->create());

    postJson("/api/channels/{$channel->id}/join")->assertForbidden();
});
