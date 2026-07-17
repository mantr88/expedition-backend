<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

it('activates account with valid token', function () {
    $user = User::factory()->create(['password' => null]);
    $token = Password::broker()->createToken($user);

    $response = $this->postJson('/set-password', [
        'email' => $user->email,
        'token' => $token,
        'name' => 'New Name',
        'password' => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);

    $response->assertStatus(204);

    $user->refresh();
    expect($user->password)->not->toBeNull()
        ->and($user->name)->toBe('New Name')
        ->and(Hash::check('newpassword', $user->password))->toBeTrue();
});

it('fails with invalid token', function () {
    $user = User::factory()->create(['password' => null]);

    $response = $this->postJson('/set-password', [
        'email' => $user->email,
        'token' => 'invalid-token',
        'name' => 'New Name',
        'password' => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});

it('prevents pending user from logging in', function () {
    $user = User::factory()->create(['password' => null]);

    $response = $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'anything',
    ]);

    $response->assertStatus(422);
});
