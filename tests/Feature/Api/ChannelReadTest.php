<?php

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * Read-маркер і unread_count (фаза B3): маркер ідемпотентний та
 * монотонний, лічильник — COUNT(id > last_read) на льоту.
 */

it('requires channel membership to mark as read', function () {
    $channel = Channel::factory()->create();
    $message = Message::factory()->for($channel)->create();

    actingAs(User::factory()->create());

    postJson("/api/channels/{$channel->id}/read", [
        'last_read_message_id' => $message->id,
    ])->assertForbidden();
});

it('counts unread messages and resets after mark-read', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $messages = Message::factory()->count(3)->for($channel)->create();

    actingAs($user);

    getJson("/api/channels/{$channel->id}")
        ->assertOk()
        ->assertJsonPath('unread_count', 3);

    postJson("/api/channels/{$channel->id}/read", [
        'last_read_message_id' => $messages->last()->id,
    ])
        ->assertOk()
        ->assertJsonPath('unread_count', 0)
        ->assertJsonPath('my_membership.last_read_message_id', $messages->last()->id);
});

it('counts only messages newer than the read marker', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $messages = Message::factory()->count(3)->for($channel)->create();

    actingAs($user);

    postJson("/api/channels/{$channel->id}/read", [
        'last_read_message_id' => $messages[0]->id,
    ])
        ->assertOk()
        ->assertJsonPath('unread_count', 2);
});

it('is monotonic: the marker never goes backwards', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $messages = Message::factory()->count(2)->for($channel)->create();

    actingAs($user);

    postJson("/api/channels/{$channel->id}/read", [
        'last_read_message_id' => $messages[1]->id,
    ])->assertOk();

    // Спроба відкотити маркер на старіше повідомлення — no-op.
    postJson("/api/channels/{$channel->id}/read", [
        'last_read_message_id' => $messages[0]->id,
    ])
        ->assertOk()
        ->assertJsonPath('my_membership.last_read_message_id', $messages[1]->id)
        ->assertJsonPath('unread_count', 0);
});

it('is idempotent for the same marker', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $message = Message::factory()->for($channel)->create();

    actingAs($user);

    postJson("/api/channels/{$channel->id}/read", ['last_read_message_id' => $message->id])->assertOk();
    postJson("/api/channels/{$channel->id}/read", ['last_read_message_id' => $message->id])
        ->assertOk()
        ->assertJsonPath('my_membership.last_read_message_id', $message->id);
});

it('rejects a marker pointing to another channel', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $foreign = Message::factory()->create();

    actingAs($user);

    postJson("/api/channels/{$channel->id}/read", [
        'last_read_message_id' => $foreign->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('last_read_message_id');
});

it('exposes unread_count per member in the channels list', function () {
    $reader = User::factory()->create();
    $channel = makeChannelFor($reader);
    Message::factory()->count(2)->for($channel)->create();

    actingAs($reader);

    getJson('/api/channels')
        ->assertOk()
        ->assertJsonPath('data.0.unread_count', 2);
});
