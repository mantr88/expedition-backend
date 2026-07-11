<?php

use App\Models\Message;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/*
 * Треди (фаза B4): пагінований список реплаїв, reply_count, last_reply_at.
 */

it('lists thread replies in chronological order', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $parent = Message::factory()->for($channel)->for($user)->create();

    $replies = Message::factory()
        ->count(3)
        ->for($channel)
        ->for($user)
        ->create(['parent_id' => $parent->id]);

    actingAs($user);

    $response = getJson("/api/messages/{$parent->id}/replies")
        ->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.has_more', false);

    expect($response->json('data.0.id'))->toBe($replies[0]->id)
        ->and($response->json('data.2.id'))->toBe($replies[2]->id);
});

it('paginates thread replies with after cursor', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $parent = Message::factory()->for($channel)->for($user)->create();

    $replies = Message::factory()
        ->count(5)
        ->for($channel)
        ->for($user)
        ->create(['parent_id' => $parent->id]);

    actingAs($user);

    $page1 = getJson("/api/messages/{$parent->id}/replies?limit=2")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.has_more', true)
        ->assertJsonPath('data.0.id', $replies[0]->id)
        ->assertJsonPath('data.1.id', $replies[1]->id);

    $cursor = $page1->json('meta.next_cursor');

    $page2 = getJson("/api/messages/{$parent->id}/replies?limit=2&after={$cursor}")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.has_more', true)
        ->assertJsonPath('data.0.id', $replies[2]->id)
        ->assertJsonPath('data.1.id', $replies[3]->id);

    $cursor2 = $page2->json('meta.next_cursor');

    getJson("/api/messages/{$parent->id}/replies?limit=2&after={$cursor2}")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('meta.next_cursor', null)
        ->assertJsonPath('data.0.id', $replies[4]->id);
});

it('shows reply_count and last_reply_at in parent message', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $parent = Message::factory()->for($channel)->for($user)->create();

    $lastReply = Message::factory()
        ->count(3)
        ->for($channel)
        ->for($user)
        ->create(['parent_id' => $parent->id])
        ->last();

    actingAs($user);

    $response = getJson("/api/channels/{$channel->id}/messages")->assertSuccessful();

    expect($response->json('data.0.reply_count'))->toBe(3)
        ->and($response->json('data.0.last_reply_at'))->toBe($lastReply->created_at->toISOString());
});

it('does not include thread replies in the channel feed', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $parent = Message::factory()->for($channel)->for($user)->create();
    Message::factory()->for($channel)->for($user)->create(['parent_id' => $parent->id]);

    actingAs($user);

    getJson("/api/channels/{$channel->id}/messages")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $parent->id);
});

it('forbids non-members to list thread replies', function () {
    $owner = User::factory()->create();
    $channel = makeChannelFor($owner);
    $parent = Message::factory()->for($channel)->for($owner)->create();

    $outsider = User::factory()->create();

    actingAs($outsider);

    getJson("/api/messages/{$parent->id}/replies")->assertForbidden();
});

it('returns 403 for replies request on a reply (not top-level)', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $parent = Message::factory()->for($channel)->for($user)->create();
    $reply = Message::factory()->for($channel)->for($user)->create(['parent_id' => $parent->id]);

    actingAs($user);

    getJson("/api/messages/{$reply->id}/replies")->assertForbidden();
});
