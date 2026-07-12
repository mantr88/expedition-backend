<?php

use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * Rate limiting (фаза B5): іменовані ліміти на надсилання повідомлень,
 * аплоади та пошук. Контракт для фронту: 429 + Retry-After і
 * X-RateLimit-* заголовки.
 */

it('exposes rate limit headers on message sending', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    postJson("/api/channels/{$channel->id}/messages", [
        'body' => 'hello',
        'client_message_id' => (string) Str::uuid(),
    ])
        ->assertCreated()
        ->assertHeader('X-RateLimit-Limit', 60)
        ->assertHeader('X-RateLimit-Remaining', 59);
});

it('throttles message sending after 60 requests per minute', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    for ($i = 0; $i < 60; $i++) {
        postJson("/api/channels/{$channel->id}/messages", [
            'body' => "msg {$i}",
            'client_message_id' => (string) Str::uuid(),
        ])->assertCreated();
    }

    postJson("/api/channels/{$channel->id}/messages", [
        'body' => 'over the limit',
        'client_message_id' => (string) Str::uuid(),
    ])
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

it('throttles search after 30 requests per minute', function () {
    $user = User::factory()->create();
    makeChannelFor($user);

    actingAs($user);

    for ($i = 0; $i < 30; $i++) {
        getJson('/api/search/messages?q=launch')->assertOk();
    }

    getJson('/api/search/messages?q=launch')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

it('does not throttle message reads', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    $response = getJson("/api/channels/{$channel->id}/messages")->assertOk();

    expect($response->headers->has('X-RateLimit-Limit'))->toBeFalse();
});

it('applies the uploads limiter to the attachments route', function () {
    // Ліміт для аплоадів перевіряємо через реєстр роутів (без 20 реальних
    // завантажень): middleware throttle:uploads має бути на роуті.
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route) => $route->getName() === 'messages.attachments.store');

    expect($route->gatherMiddleware())->toContain('throttle:uploads');
});
