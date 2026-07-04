<?php

use App\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('logs in with valid credentials and returns the current user', function () {
    $user = User::factory()->create(['email' => 'anna@example.com']);

    postJson('/login', [
        'email' => 'anna@example.com',
        'password' => 'password',
    ])->assertNoContent();

    getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('email', 'anna@example.com');
});

it('rejects invalid credentials with a standard 422 validation error', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    postJson('/login', [
        'email' => 'anna@example.com',
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors' => ['email']]);
});

it('returns 401 for unauthenticated /api/user', function () {
    getJson('/api/user')->assertUnauthorized();
});

it('logs out and invalidates the session', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    postJson('/login', ['email' => 'anna@example.com', 'password' => 'password'])
        ->assertNoContent();

    postJson('/logout')->assertNoContent();

    getJson('/api/user')->assertUnauthorized();
});
