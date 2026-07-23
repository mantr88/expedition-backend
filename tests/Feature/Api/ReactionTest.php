<?php

use App\Events\ReactionToggled;
use App\Models\ChannelMember;
use App\Models\Message;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * Реакції (фаза B4): toggle, агрегація per-user, broadcast.
 */

it('adds a reaction to a message', function () {
    Event::fake([ReactionToggled::class]);

    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $message = Message::factory()->for($channel)->for($user)->create();

    actingAs($user);

    $response = postJson("/api/messages/{$message->id}/reactions", [
        'emoji' => '👍',
    ])->assertSuccessful();

    expect($response->json('action'))->toBe('added')
        ->and($response->json('count'))->toBe(1)
        ->and(Reaction::count())->toBe(1);

    Event::assertDispatched(ReactionToggled::class, function (ReactionToggled $event) use ($message, $user): bool {
        return $event->message->id === $message->id
            && $event->emoji === '👍'
            && $event->count === 1
            && $event->userId === $user->id
            && $event->userName === $user->name
            && $event->action === 'added';
    });
});

it('removes a reaction on second toggle', function () {
    Event::fake([ReactionToggled::class]);

    $user = User::factory()->create();
    $channel = makeChannelFor($user);
    $message = Message::factory()->for($channel)->for($user)->create();

    actingAs($user);

    postJson("/api/messages/{$message->id}/reactions", ['emoji' => '👍'])->assertSuccessful();
    $response = postJson("/api/messages/{$message->id}/reactions", ['emoji' => '👍'])->assertSuccessful();

    expect($response->json('action'))->toBe('removed')
        ->and($response->json('count'))->toBe(0)
        ->and(Reaction::count())->toBe(0);
});

it('tracks reacted_by_me correctly per user in MessageResource', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $channel = makeChannelFor($user);
    ChannelMember::factory()->for($channel)->for($other)->create();
    $message = Message::factory()->for($channel)->for($user)->create();

    Reaction::factory()->for($message)->for($other)->create(['emoji' => '👍']);

    actingAs($user);

    $response = getJson("/api/channels/{$channel->id}/messages")->assertSuccessful();
    $reactions = $response->json('data.0.reactions');

    expect($reactions)->toHaveCount(1)
        ->and($reactions[0]['emoji'])->toBe('👍')
        ->and($reactions[0]['count'])->toBe(1)
        ->and($reactions[0]['reacted_by_me'])->toBeFalse()
        ->and($reactions[0]['users'])->toBe([$other->name]);

    Reaction::factory()->for($message)->for($user)->create(['emoji' => '👍']);

    $response = getJson("/api/channels/{$channel->id}/messages")->assertSuccessful();
    $reactions = $response->json('data.0.reactions');

    expect($reactions[0]['count'])->toBe(2)
        ->and($reactions[0]['reacted_by_me'])->toBeTrue()
        ->and($reactions[0]['users'])->toContain($other->name, $user->name);
});

it('forbids non-members to react', function () {
    $owner = User::factory()->create();
    $channel = makeChannelFor($owner);
    $message = Message::factory()->for($channel)->for($owner)->create();

    $outsider = User::factory()->create();

    actingAs($outsider);

    postJson("/api/messages/{$message->id}/reactions", ['emoji' => '👍'])->assertForbidden();
});
