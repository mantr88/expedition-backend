<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/*
 * Пошук користувачів для DM/інвайтів (фаза B3).
 */

it('requires authentication', function () {
    getJson('/api/users?query=abc')->assertUnauthorized();
});

it('finds users by partial name case-insensitively', function () {
    $me = User::factory()->create();
    $match = User::factory()->create(['name' => 'Taras Hryhorovych']);
    User::factory()->create(['name' => 'Інша Людина']);

    actingAs($me);

    getJson('/api/users?query=taras')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id)
        ->assertJsonPath('data.0.name', 'Taras Hryhorovych');
});

it('finds users by a partial cyrillic name', function () {
    $me = User::factory()->create();
    $match = User::factory()->create(['name' => 'Тарас Григорович']);

    actingAs($me);

    getJson('/api/users?query='.urlencode('Тарас'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

it('finds users by email', function () {
    $me = User::factory()->create();
    $match = User::factory()->create(['email' => 'taras@expedition.test']);

    actingAs($me);

    getJson('/api/users?query=taras@')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

it('excludes the current user from results', function () {
    $me = User::factory()->create(['name' => 'Самопошук Тест']);

    actingAs($me);

    getJson('/api/users?query=Самопошук')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns everyone but me without a query', function () {
    $me = User::factory()->create();
    User::factory()->count(3)->create();

    actingAs($me);

    getJson('/api/users')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('rejects an overlong query', function () {
    actingAs(User::factory()->create());

    getJson('/api/users?query='.str_repeat('a', 101))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('query');
});
