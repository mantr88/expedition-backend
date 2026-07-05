<?php

use App\Events\AddedToChannel;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * DM (фаза B3): канал із type=dm і двома учасниками; відкриття
 * ідемпотентне, name у ресурсі — ім'я співрозмовника.
 */

it('requires authentication', function () {
    postJson('/api/direct-messages', ['user_id' => 1])->assertUnauthorized();
});

it('creates a dm channel with both participants', function () {
    $me = User::factory()->create();
    $other = User::factory()->create(['name' => 'Оксана Шевченко']);

    actingAs($me);

    $response = postJson('/api/direct-messages', ['user_id' => $other->id]);

    $response->assertCreated()
        ->assertJsonPath('type', 'dm')
        ->assertJsonPath('name', 'Оксана Шевченко')
        ->assertJsonPath('members_count', 2)
        ->assertJsonPath('unread_count', 0)
        ->assertJsonPath('my_membership.role', 'member');

    $channel = Channel::findOrFail($response->json('id'));
    expect($channel->isMember($me))->toBeTrue()
        ->and($channel->isMember($other))->toBeTrue();
});

it('returns the same channel when the dm is opened again from either side', function () {
    $me = User::factory()->create(['name' => 'Андрій Мельник']);
    $other = User::factory()->create();

    actingAs($me);
    $first = postJson('/api/direct-messages', ['user_id' => $other->id])->assertCreated();
    $repeat = postJson('/api/direct-messages', ['user_id' => $other->id])->assertOk();

    actingAs($other);
    $mirrored = postJson('/api/direct-messages', ['user_id' => $me->id])->assertOk();

    expect($repeat->json('id'))->toBe($first->json('id'))
        ->and($mirrored->json('id'))->toBe($first->json('id'))
        // Резолвер імені — per-viewer: співрозмовник бачить ім'я ініціатора.
        ->and($mirrored->json('name'))->toBe('Андрій Мельник');
});

it('rejects a dm with yourself and with an unknown user', function () {
    $me = User::factory()->create();

    actingAs($me);

    postJson('/api/direct-messages', ['user_id' => $me->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_id');

    postJson('/api/direct-messages', ['user_id' => 999999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_id');
});

it('broadcasts AddedToChannel to the counterpart only on first open', function () {
    Event::fake([AddedToChannel::class]);

    $me = User::factory()->create(['name' => 'Ініціатор Розмови']);
    $other = User::factory()->create();

    actingAs($me);
    postJson('/api/direct-messages', ['user_id' => $other->id])->assertCreated();
    postJson('/api/direct-messages', ['user_id' => $other->id])->assertOk();

    Event::assertDispatchedTimes(AddedToChannel::class, 1);

    $event = null;
    Event::assertDispatched(AddedToChannel::class, function (AddedToChannel $dispatched) use (&$event): bool {
        $event = $dispatched;

        return true;
    });

    $payload = json_decode(json_encode($event->broadcastWith()), true);

    expect($event->broadcastOn()->name)->toBe("private-user.{$other->id}")
        ->and($event->broadcastAs())->toBe('AddedToChannel')
        // Ім'я DM у payload резолвиться відносно отримувача події.
        ->and($payload['channel']['name'])->toBe('Ініціатор Розмови')
        ->and($payload['channel']['type'])->toBe('dm')
        // Per-viewer поля у broadcast завжди null.
        ->and($payload['channel']['my_membership'])->toBeNull()
        ->and($payload['channel']['unread_count'])->toBeNull();
});

it('lists the dm with the counterpart name in the channels index', function () {
    $me = User::factory()->create();
    $other = User::factory()->create(['name' => 'Марія Коваль']);

    actingAs($me);
    postJson('/api/direct-messages', ['user_id' => $other->id])->assertCreated();

    getJson('/api/channels')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'dm')
        ->assertJsonPath('data.0.name', 'Марія Коваль');
});
