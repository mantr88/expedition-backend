<?php

use App\Models\User;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/*
 * Виконуваний контракт UserResource (фаза B0): базова форма користувача,
 * на яку посилаються всі інші ресурси. Зміна форми = зміна контракту з фронтом.
 */

it('serializes the exact UserResource contract shape', function () {
    $user = User::factory()->create([
        'name' => 'Anna Petrenko',
        'email' => 'anna@example.com',
        'avatar_url' => 'https://cdn.example.com/avatars/1.png',
        'status' => 'active',
        'last_seen_at' => Carbon::parse('2026-07-03T12:00:00Z'),
    ]);

    actingAs($user);

    getJson('/api/user')->assertOk()->assertExactJson([
        'id' => $user->id,
        'name' => 'Anna Petrenko',
        'email' => 'anna@example.com',
        'avatar_url' => 'https://cdn.example.com/avatars/1.png',
        'status' => 'active',
        'last_seen_at' => '2026-07-03T12:00:00.000000Z',
        'is_pending' => false,
    ]);
});

it('serializes nullable fields as null, not missing keys', function () {
    $user = User::factory()->create([
        'avatar_url' => null,
        'last_seen_at' => null,
    ]);

    actingAs($user);

    getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('avatar_url', null)
        ->assertJsonPath('last_seen_at', null);
});
