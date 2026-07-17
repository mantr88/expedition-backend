<?php

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

/*
 * Повідомлення (фаза B1): надсилання з дедуплікацією client_message_id,
 * курсорна пагінація, санітизація markdown, редагування і soft delete.
 */

it('sends a message and serializes the exact MessageResource contract shape', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $clientMessageId = (string) Str::uuid();

    actingAs($user);

    $response = postJson("/api/channels/{$channel->id}/messages", [
        'body' => 'Привіт, **команда**!',
        'client_message_id' => $clientMessageId,
    ])->assertCreated();

    $message = Message::findOrFail($response->json('id'));

    $response->assertExactJson([
        'id' => $message->id,
        'client_message_id' => $clientMessageId,
        'channel_id' => $channel->id,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
            'last_seen_at' => $user->last_seen_at?->toISOString(),
            'is_pending' => false,
        ],
        'parent_id' => null,
        'body_html' => 'Привіт, <strong>команда</strong>!',
        'body_raw' => 'Привіт, **команда**!',
        'type' => 'text',
        'edited_at' => null,
        'deleted_at' => null,
        'created_at' => $message->created_at->toISOString(),
        'reactions' => [],
        'attachments' => [],
        'reply_count' => 0,
        'last_reply_at' => null,
    ]);
});

it('does not create a duplicate for a repeated client_message_id', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $payload = [
        'body' => 'єдине повідомлення',
        'client_message_id' => (string) Str::uuid(),
    ];

    actingAs($user);

    $first = postJson("/api/channels/{$channel->id}/messages", $payload)->assertCreated();
    $second = postJson("/api/channels/{$channel->id}/messages", $payload)->assertOk();

    expect($second->json('id'))->toBe($first->json('id'))
        ->and(Message::count())->toBe(1);
});

it('rejects a client_message_id already used in another channel', function () {
    $user = User::factory()->create();
    $channelA = makeChannelFor($user);
    $channelB = makeChannelFor($user);
    $clientMessageId = (string) Str::uuid();

    actingAs($user);

    postJson("/api/channels/{$channelA->id}/messages", [
        'body' => 'перше',
        'client_message_id' => $clientMessageId,
    ])->assertCreated();

    postJson("/api/channels/{$channelB->id}/messages", [
        'body' => 'друге',
        'client_message_id' => $clientMessageId,
    ])->assertUnprocessable();

    expect(Message::count())->toBe(1);
});

it('forbids non-members to read or post messages', function () {
    $channel = Channel::factory()->create();

    actingAs(User::factory()->create());

    getJson("/api/channels/{$channel->id}/messages")->assertForbidden();

    postJson("/api/channels/{$channel->id}/messages", [
        'body' => 'привіт',
        'client_message_id' => (string) Str::uuid(),
    ])->assertForbidden();
});

it('forbids posting into an archived channel', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $channel->update(['archived_at' => now()]);

    actingAs($user);

    postJson("/api/channels/{$channel->id}/messages", [
        'body' => 'запізно',
        'client_message_id' => (string) Str::uuid(),
    ])->assertForbidden();
});

it('sanitizes markdown with script tags (XSS regression)', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    $response = postJson("/api/channels/{$channel->id}/messages", [
        'body' => 'Inject: <script>alert("xss")</script>',
        'client_message_id' => (string) Str::uuid(),
    ])->assertCreated();

    expect($response->json('body_html'))
        ->not->toContain('<script')
        ->toContain('&lt;script&gt;')
        ->and($response->json('body_raw'))->toContain('<script>');
});

it('validates the message payload', function (array $payload) {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    postJson("/api/channels/{$channel->id}/messages", $payload)->assertUnprocessable();
})->with([
    'without body' => [['client_message_id' => '550e8400-e29b-41d4-a716-446655440000']],
    'without client_message_id' => [['body' => 'x']],
    'client_message_id is not a uuid' => [['body' => 'x', 'client_message_id' => 'not-a-uuid']],
    'body too long' => fn () => [['body' => str_repeat('а', 4001), 'client_message_id' => (string) Str::uuid()]],
]);

it('accepts a thread reply with a parent from the same channel', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $parent = Message::factory()->create(['channel_id' => $channel->id, 'user_id' => $user->id]);

    actingAs($user);

    postJson("/api/channels/{$channel->id}/messages", [
        'body' => 'відповідь у тред',
        'client_message_id' => (string) Str::uuid(),
        'parent_id' => $parent->id,
    ])->assertCreated()->assertJsonPath('parent_id', $parent->id);
});

it('rejects a parent message from another channel', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $foreignParent = Message::factory()->create();

    actingAs($user);

    postJson("/api/channels/{$channel->id}/messages", [
        'body' => 'відповідь',
        'client_message_id' => (string) Str::uuid(),
        'parent_id' => $foreignParent->id,
    ])->assertUnprocessable();
});

it('rejects a reply to a reply (no nested threads)', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $parent = Message::factory()->create(['channel_id' => $channel->id, 'user_id' => $user->id]);
    $reply = Message::factory()->create([
        'channel_id' => $channel->id,
        'user_id' => $user->id,
        'parent_id' => $parent->id,
    ]);

    actingAs($user);

    postJson("/api/channels/{$channel->id}/messages", [
        'body' => 'вкладена відповідь',
        'client_message_id' => (string) Str::uuid(),
        'parent_id' => $reply->id,
    ])->assertUnprocessable();
});

it('paginates messages by cursor with stable order and correct has_more', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $messages = Message::factory()
        ->count(5)
        ->create(['channel_id' => $channel->id, 'user_id' => $user->id]);
    [$m1, $m2, $m3, $m4, $m5] = $messages->all();

    actingAs($user);

    $pageOne = getJson("/api/channels/{$channel->id}/messages?limit=2")
        ->assertOk()
        ->assertJsonPath('data.0.id', $m5->id)
        ->assertJsonPath('data.1.id', $m4->id)
        ->assertJsonPath('meta.has_more', true)
        ->assertJsonPath('meta.next_cursor', $m4->id);

    $cursor = $pageOne->json('meta.next_cursor');

    getJson("/api/channels/{$channel->id}/messages?limit=2&before={$cursor}")
        ->assertOk()
        ->assertJsonPath('data.0.id', $m3->id)
        ->assertJsonPath('data.1.id', $m2->id)
        ->assertJsonPath('meta.has_more', true)
        ->assertJsonPath('meta.next_cursor', $m2->id);

    getJson("/api/channels/{$channel->id}/messages?limit=2&before={$m2->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $m1->id)
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('meta.next_cursor', null);
});

it('excludes thread replies and deleted messages from the channel feed', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $visible = Message::factory()->create(['channel_id' => $channel->id, 'user_id' => $user->id]);
    Message::factory()->create([
        'channel_id' => $channel->id,
        'user_id' => $user->id,
        'parent_id' => $visible->id,
    ]);
    Message::factory()
        ->create(['channel_id' => $channel->id, 'user_id' => $user->id])
        ->delete();

    actingAs($user);

    getJson("/api/channels/{$channel->id}/messages")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->id);
});

it('lets the author edit a message', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $message = Message::factory()->create(['channel_id' => $channel->id, 'user_id' => $user->id]);

    actingAs($user);

    $response = patchJson("/api/messages/{$message->id}", ['body' => 'виправлено **текст**'])
        ->assertOk()
        ->assertJsonPath('body_raw', 'виправлено **текст**')
        ->assertJsonPath('body_html', 'виправлено <strong>текст</strong>');

    expect($response->json('edited_at'))->not->toBeNull();
});

it('forbids editing someone else\'s message', function () {
    $author = User::factory()->create();
    $channel = makeChannelFor($author);
    $message = Message::factory()->create(['channel_id' => $channel->id, 'user_id' => $author->id]);

    $intruder = User::factory()->create();
    makeChannelFor($intruder);

    actingAs($intruder);

    patchJson("/api/messages/{$message->id}", ['body' => 'hack'])->assertForbidden();
});

it('returns 404 when editing a deleted message', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $message = Message::factory()->create(['channel_id' => $channel->id, 'user_id' => $user->id]);
    $message->delete();

    actingAs($user);

    patchJson("/api/messages/{$message->id}", ['body' => 'пізно'])->assertNotFound();
});

it('soft deletes a message by its author', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $message = Message::factory()->create(['channel_id' => $channel->id, 'user_id' => $user->id]);

    actingAs($user);

    deleteJson("/api/messages/{$message->id}")->assertNoContent();

    $this->assertSoftDeleted($message);
});

it('lets a channel owner delete someone else\'s message', function () {
    $owner = User::factory()->create();
    $channel = makeChannelFor($owner, role: 'owner');
    $author = User::factory()->create();
    $message = Message::factory()->create(['channel_id' => $channel->id, 'user_id' => $author->id]);

    actingAs($owner);

    deleteJson("/api/messages/{$message->id}")->assertNoContent();

    $this->assertSoftDeleted($message);
});

it('forbids a regular member to delete someone else\'s message', function () {
    $author = User::factory()->create();
    $channel = makeChannelFor($author);
    $message = Message::factory()->create(['channel_id' => $channel->id, 'user_id' => $author->id]);

    $member = User::factory()->create();
    ChannelMember::factory()->for($channel)->for($member)->create();

    actingAs($member);

    deleteJson("/api/messages/{$message->id}")->assertForbidden();
});
