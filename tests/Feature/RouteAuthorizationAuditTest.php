<?php

use Illuminate\Support\Facades\Route;

/*
 * Аудит авторизації (фаза B5): жоден api-роут, крім явного allowlist,
 * не лишається без auth:sanctum. «Голий» роут = падіння CI.
 * Аудит охоплює лише api/*; non-api поверхня (/up, /pulse,
 * livewire-роути) — поза скоупом, /pulse захищений viewPulse-гейтом.
 */

it('protects every api route with sanctum auth except the allowlist', function () {
    $allowlist = [
        'api/health', // публічний healthcheck
    ];

    $unprotected = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/'))
        ->reject(fn ($route): bool => in_array($route->uri(), $allowlist, true))
        ->reject(fn ($route): bool => in_array('auth:sanctum', $route->gatherMiddleware(), true))
        ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values();

    expect($unprotected->all())->toBe([]);
});
