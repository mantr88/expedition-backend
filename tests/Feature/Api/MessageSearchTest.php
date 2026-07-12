<?php

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/*
 * Пошук повідомлень (фаза B5): ізоляція прав членства, фільтр каналу,
 * курсорна пагінація. У тестах (sqlite) працює LIKE-фолбек;
 * FTS-гілка (tsvector + GIN) — лише на Postgres.
 */

it('requires authentication', function () {
    getJson('/api/search/messages?q=hello')->assertUnauthorized();
});

it('finds messages by a word with channel context', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $match = Message::factory()->for($channel)->create(['body' => 'expedition launch checklist']);
    Message::factory()->for($channel)->create(['body' => 'unrelated chatter']);

    actingAs($user);

    getJson('/api/search/messages?q=launch')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id)
        ->assertJsonPath('data.0.body_raw', 'expedition launch checklist')
        ->assertJsonPath('data.0.channel.id', $channel->id)
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('meta.next_cursor', null);
});

it('does not leak messages from channels the user is not a member of', function () {
    $user = User::factory()->create();
    makeChannelFor($user);

    $foreignChannel = Channel::factory()->create();
    Message::factory()->for($foreignChannel)->create(['body' => 'secret launch codes']);

    actingAs($user);

    getJson('/api/search/messages?q=launch')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('filters by channel_id', function () {
    $user = User::factory()->create();
    $first = makeChannelFor($user);
    $second = makeChannelFor($user);
    Message::factory()->for($first)->create(['body' => 'launch from first']);
    $expected = Message::factory()->for($second)->create(['body' => 'launch from second']);

    actingAs($user);

    getJson("/api/search/messages?q=launch&channel_id={$second->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $expected->id);
});

it('paginates with a cursor', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $messages = collect(range(1, 3))
        ->map(fn (int $i) => Message::factory()->for($channel)->create(['body' => "launch update {$i}"]));

    actingAs($user);

    $firstPage = getJson('/api/search/messages?q=launch&limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $messages[2]->id)
        ->assertJsonPath('meta.has_more', true)
        ->assertJsonPath('meta.next_cursor', $messages[1]->id);

    getJson('/api/search/messages?q=launch&limit=2&before='.$firstPage->json('meta.next_cursor'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $messages[0]->id)
        ->assertJsonPath('meta.has_more', false);
});

it('excludes soft-deleted messages', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $message = Message::factory()->for($channel)->create(['body' => 'launch then delete']);
    $message->delete();

    actingAs($user);

    getJson('/api/search/messages?q=launch')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('validates the query string', function () {
    actingAs(User::factory()->create());

    getJson('/api/search/messages')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');

    getJson('/api/search/messages?q=a')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});
