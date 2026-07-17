<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a user via command', function () {
    $this->artisan('user:create', [
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        '--password' => 'secret123',
    ])->assertSuccessful()
        ->expectsOutputToContain('created successfully')
        ->expectsOutputToContain('secret123');

    $user = User::where('email', 'admin@example.com')->first();
    expect($user)->not->toBeNull();
    expect(Hash::check('secret123', $user->password))->toBeTrue();
});

it('fails if user exists', function () {
    User::factory()->create(['email' => 'exist@example.com']);

    $this->artisan('user:create', [
        'name' => 'Exists',
        'email' => 'exist@example.com',
    ])->assertFailed()
        ->expectsOutputToContain('already exists');
});
