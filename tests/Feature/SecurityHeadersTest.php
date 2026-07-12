<?php

use function Pest\Laravel\getJson;

/*
 * Захисні заголовки (фаза B5): API-відповіді несуть CSP та
 * anti-sniffing/clickjacking заголовки.
 */

it('adds security headers to api responses', function () {
    getJson('/api/health')
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});
