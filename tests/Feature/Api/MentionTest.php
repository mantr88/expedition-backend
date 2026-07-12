<?php

use App\Events\Mentioned;
use App\Models\Mention;
use App\Models\User;
use App\Notifications\MentionEmailDigest;
use App\Support\Presence;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

/*
 * Згадки @user (фаза B3): запис у mentions, подія на private-user.{id}
 * згаданого, email-дайджест офлайн-користувачу з debounce.
 */

/**
 * Підміна Presence: тести керують «онлайн/офлайн» без Reverb.
 */
function fakePresence(bool $online): void
{
    app()->instance(Presence::class, new readonly class($online) implements Presence
    {
        public function __construct(private bool $online) {}

        public function isOnline(User $user): bool
        {
            return $this->online;
        }
    });
}

function sendMessageWithBody(int $channelId, string $body): TestResponse
{
    return postJson("/api/channels/{$channelId}/messages", [
        'body' => $body,
        'client_message_id' => (string) Str::uuid(),
    ]);
}

it('records a mention and broadcasts Mentioned to the mentioned user', function () {
    Event::fake([Mentioned::class]);
    fakePresence(online: true);

    $author = User::factory()->create();
    $mentioned = User::factory()->create(['name' => 'Олена Ткаченко']);
    $channel = makeChannelFor($author);
    $channel->members()->create(['user_id' => $mentioned->id, 'role' => 'member']);

    actingAs($author);

    $response = sendMessageWithBody($channel->id, 'Привіт, @Олена Ткаченко, глянь тред')->assertCreated();

    expect(Mention::query()->where('mentioned_user_id', $mentioned->id)->where('message_id', $response->json('id'))->exists())->toBeTrue();

    $event = null;
    Event::assertDispatched(Mentioned::class, function (Mentioned $dispatched) use (&$event): bool {
        $event = $dispatched;

        return true;
    });

    $payload = json_decode(json_encode($event->broadcastWith()), true);

    expect($event->broadcastOn()->name)->toBe("private-user.{$mentioned->id}")
        ->and($event->broadcastAs())->toBe('Mentioned')
        ->and($payload['message']['id'])->toBe($response->json('id'))
        ->and($payload['channel']['id'])->toBe($channel->id);
});

it('ignores self-mentions and non-members', function () {
    Event::fake([Mentioned::class]);
    fakePresence(online: true);

    $author = User::factory()->create(['name' => 'Автор Сам']);
    $outsider = User::factory()->create(['name' => 'Сторонній Користувач']);
    $channel = makeChannelFor($author);

    actingAs($author);

    sendMessageWithBody($channel->id, 'тут @Автор Сам і @Сторонній Користувач')->assertCreated();

    expect(Mention::count())->toBe(0);
    Event::assertNotDispatched(Mentioned::class);
});

it('queues an email digest for an offline mentioned user', function () {
    Notification::fake();
    fakePresence(online: false);

    $author = User::factory()->create();
    $mentioned = User::factory()->create(['name' => 'Офлайн Юзер']);
    $channel = makeChannelFor($author);
    $channel->members()->create(['user_id' => $mentioned->id, 'role' => 'member']);

    actingAs($author);

    sendMessageWithBody($channel->id, 'агов @Офлайн Юзер')->assertCreated();

    Notification::assertSentTo($mentioned, MentionEmailDigest::class);
});

it('does not queue an email for an online mentioned user', function () {
    Notification::fake();
    fakePresence(online: true);

    $author = User::factory()->create();
    $mentioned = User::factory()->create(['name' => 'Онлайн Юзер']);
    $channel = makeChannelFor($author);
    $channel->members()->create(['user_id' => $mentioned->id, 'role' => 'member']);

    actingAs($author);

    sendMessageWithBody($channel->id, 'агов @Онлайн Юзер')->assertCreated();

    Notification::assertNothingSent();
});

it('debounces the digest: several mentions in a row produce one email job', function () {
    Notification::fake();
    fakePresence(online: false);

    $author = User::factory()->create();
    $mentioned = User::factory()->create(['name' => 'Зайнятий Колега']);
    $channel = makeChannelFor($author);
    $channel->members()->create(['user_id' => $mentioned->id, 'role' => 'member']);

    actingAs($author);

    sendMessageWithBody($channel->id, 'перше @Зайнятий Колега')->assertCreated();
    sendMessageWithBody($channel->id, 'друге @Зайнятий Колега')->assertCreated();
    sendMessageWithBody($channel->id, 'третє @Зайнятий Колега')->assertCreated();

    Notification::assertSentToTimes($mentioned, MentionEmailDigest::class, 1);
    expect(Mention::query()->where('mentioned_user_id', $mentioned->id)->count())->toBe(3);
});

it('is not broken by injection-like bodies', function () {
    Event::fake([Mentioned::class]);
    fakePresence(online: true);

    $author = User::factory()->create();
    $mentioned = User::factory()->create(['name' => 'Ціль Атаки']);
    $channel = makeChannelFor($author);
    $channel->members()->create(['user_id' => $mentioned->id, 'role' => 'member']);

    actingAs($author);

    // Спецсимволи regex/HTML у тілі не ламають парсер і не дають false-збігів.
    sendMessageWithBody($channel->id, '@<script>alert(1)</script> @[a-z]+.* (@ @@) і справжня @Ціль Атаки')
        ->assertCreated();

    expect(Mention::query()->where('mentioned_user_id', $mentioned->id)->count())->toBe(1);
    Event::assertDispatchedTimes(Mentioned::class, 1);
});

it('does not queue an email digest for a mention in a muted channel', function () {
    Notification::fake();
    Event::fake([Mentioned::class]);
    fakePresence(online: false);

    $author = User::factory()->create();
    $mentioned = User::factory()->create(['name' => 'Мьютнутий Канал']);
    $channel = makeChannelFor($author);
    $channel->members()->create([
        'user_id' => $mentioned->id,
        'role' => 'member',
        'notifications_level' => 'mute',
    ]);

    actingAs($author);

    sendMessageWithBody($channel->id, 'агов @Мьютнутий Канал')->assertCreated();

    // Запис згадки і realtime-подія лишаються — mute стосується лише email.
    expect(Mention::query()->where('mentioned_user_id', $mentioned->id)->count())->toBe(1);
    Notification::assertNothingSent();
    Event::assertDispatched(Mentioned::class);
});

it('excludes muted channels from the mention digest email while keeping non-muted ones', function () {
    Notification::fake();
    fakePresence(online: false);

    $author = User::factory()->create();
    $mentioned = User::factory()->create(['name' => 'Двоканальний Юзер']);

    $mutedChannel = makeChannelFor($author);
    $mutedChannel->update(['name' => 'Замьючений Канал']);
    $mutedChannel->members()->create([
        'user_id' => $mentioned->id,
        'role' => 'member',
        'notifications_level' => 'mute',
    ]);

    $activeChannel = makeChannelFor($author);
    $activeChannel->update(['name' => 'Активний Канал']);
    $activeChannel->members()->create([
        'user_id' => $mentioned->id,
        'role' => 'member',
    ]);

    actingAs($author);

    sendMessageWithBody($mutedChannel->id, 'агов @Двоканальний Юзер')->assertCreated();
    sendMessageWithBody($activeChannel->id, 'агов @Двоканальний Юзер')->assertCreated();

    Notification::assertSentToTimes($mentioned, MentionEmailDigest::class, 1);

    $notification = null;
    Notification::assertSentTo($mentioned, MentionEmailDigest::class, function (MentionEmailDigest $sent) use (&$notification): bool {
        $notification = $sent;

        return true;
    });

    $mailLines = implode("\n", $notification->toMail($mentioned)->introLines);

    expect($mailLines)
        ->toContain('Активний Канал')
        ->not->toContain('Замьючений Канал');
});
