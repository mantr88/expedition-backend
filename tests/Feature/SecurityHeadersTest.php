<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * Захисні заголовки (фаза B5): усі відповіді api/* несуть CSP та
 * anti-sniffing/clickjacking заголовки — включно з помилками
 * (401/404/422/429). Web-роути не чіпаємо.
 */

it('adds security headers to api responses', function () {
    getJson('/api/health')
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

it('adds security headers to 401 responses from auth middleware', function () {
    getJson('/api/user')
        ->assertUnauthorized()
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

it('adds security headers to 404 responses for unmatched api routes', function () {
    getJson('/api/nonexistent-route')
        ->assertNotFound()
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

it('adds security headers to 422 validation error responses', function () {
    $user = User::factory()->create();
    $channel = makeChannelFor($user);

    actingAs($user);

    postJson("/api/channels/{$channel->id}/messages", [])
        ->assertUnprocessable()
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

it('does not add security headers to web routes', function () {
    $response = get('/up');

    $response->assertOk();

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse();
});
