<?php

use App\Events\ChannelUpdated;
use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

/*
 * Broadcast-події (фаза B2): диспатч з Actions, канал private-channel.{id},
 * payload ідентичний REST-ресурсу тієї ж сутності (контракт для дедупу
 * на фронті). Payload нормалізуємо через json_encode — саме ця форма
 * летить по WebSocket.
 */

/**
 * @return array<string, mixed>
 */
function broadcastPayload(object $event): array
{
    return json_decode(json_encode($event->broadcastWith()), true);
}

it('broadcasts MessageSent to the channel with a payload identical to the REST resource', function () {
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $clientMessageId = (string) Str::uuid();

    actingAs($user);

    $response = postJson("/api/channels/{$channel->id}/messages", [
        'body' => 'realtime **привіт**',
        'client_message_id' => $clientMessageId,
    ])->assertCreated();

    $event = null;
    Event::assertDispatched(MessageSent::class, function (MessageSent $dispatched) use (&$event): bool {
        $event = $dispatched;

        return true;
    });

    expect($event->broadcastOn()->name)->toBe("private-channel.{$channel->id}")
        ->and($event->broadcastAs())->toBe('MessageSent')
        ->and(broadcastPayload($event))->toEqual($response->json())
        ->and(broadcastPayload($event)['client_message_id'])->toBe($clientMessageId);
});

it('does not broadcast MessageSent again for a deduplicated client_message_id', function () {
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $payload = [
        'body' => 'єдина подія',
        'client_message_id' => (string) Str::uuid(),
    ];

    actingAs($user);

    postJson("/api/channels/{$channel->id}/messages", $payload)->assertCreated();
    postJson("/api/channels/{$channel->id}/messages", $payload)->assertOk();

    Event::assertDispatchedTimes(MessageSent::class, 1);
});

it('broadcasts MessageUpdated with the edited resource payload', function () {
    Event::fake([MessageUpdated::class]);

    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $message = Message::factory()->for($channel)->for($user)->create();

    actingAs($user);

    $response = patchJson("/api/messages/{$message->id}", [
        'body' => 'відредаговано',
    ])->assertSuccessful();

    $event = null;
    Event::assertDispatched(MessageUpdated::class, function (MessageUpdated $dispatched) use (&$event): bool {
        $event = $dispatched;

        return true;
    });

    expect($event->broadcastOn()->name)->toBe("private-channel.{$channel->id}")
        ->and($event->broadcastAs())->toBe('MessageUpdated')
        ->and(broadcastPayload($event))->toEqual($response->json())
        ->and(broadcastPayload($event)['edited_at'])->not->toBeNull();
});

it('broadcasts MessageDeleted with deleted_at in the payload', function () {
    Event::fake([MessageDeleted::class]);

    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $message = Message::factory()->for($channel)->for($user)->create();

    actingAs($user);

    deleteJson("/api/messages/{$message->id}")->assertNoContent();

    $event = null;
    Event::assertDispatched(MessageDeleted::class, function (MessageDeleted $dispatched) use (&$event): bool {
        $event = $dispatched;

        return true;
    });

    $payload = broadcastPayload($event);

    expect($event->broadcastOn()->name)->toBe("private-channel.{$channel->id}")
        ->and($event->broadcastAs())->toBe('MessageDeleted')
        ->and($payload['id'])->toBe($message->id)
        ->and($payload['deleted_at'])->not->toBeNull();
});

it('broadcasts ChannelUpdated on channel update with the REST resource shape', function () {
    Event::fake([ChannelUpdated::class]);

    $user = User::factory()->create();
    $channel = makeChannelFor($user, role: 'owner');

    actingAs($user);

    patchJson("/api/channels/{$channel->id}", [
        'name' => 'renamed-channel',
    ])->assertSuccessful();

    $event = null;
    Event::assertDispatched(ChannelUpdated::class, function (ChannelUpdated $dispatched) use (&$event): bool {
        $event = $dispatched;

        return true;
    });

    $payload = broadcastPayload($event);

    expect($event->broadcastOn()->name)->toBe("private-channel.{$channel->id}")
        ->and($event->broadcastAs())->toBe('ChannelUpdated')
        ->and($payload['id'])->toBe($channel->id)
        ->and($payload['name'])->toBe('renamed-channel')
        ->and($payload['members_count'])->toBe(1)
        // Per-viewer поле: у broadcast завжди null, фронт бере членство з REST.
        ->and($payload['my_membership'])->toBeNull();
});

it('broadcasts ChannelUpdated once for idempotent archiving', function () {
    Event::fake([ChannelUpdated::class]);

    $user = User::factory()->create();
    $channel = makeChannelFor($user, role: 'owner');

    actingAs($user);

    deleteJson("/api/channels/{$channel->id}")->assertSuccessful();
    deleteJson("/api/channels/{$channel->id}")->assertSuccessful();

    Event::assertDispatchedTimes(ChannelUpdated::class, 1);

    $event = null;
    Event::assertDispatched(ChannelUpdated::class, function (ChannelUpdated $dispatched) use (&$event): bool {
        $event = $dispatched;

        return true;
    });

    expect(broadcastPayload($event)['archived_at'])->not->toBeNull();
});
