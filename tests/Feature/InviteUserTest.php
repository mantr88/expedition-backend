<?php

use App\Mail\UserInvitedMail;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

it('creates a pending user and queues email', function () {
    Mail::fake();
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/invitations', [
        'email' => 'newuser@example.com',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('is_pending', true);

    $this->assertDatabaseHas('users', [
        'email' => 'newuser@example.com',
        'password' => null,
    ]);

    Mail::assertQueued(UserInvitedMail::class, function ($mail) {
        return $mail->hasTo('newuser@example.com');
    });
});

it('adds user to channel if channel_id is provided', function () {
    Mail::fake();
    $user = User::factory()->create();
    $channel = Channel::factory()->create(['type' => 'public']);
    $channel->members()->create(['user_id' => $user->id, 'role' => 'member']);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/invitations', [
        'email' => 'newuser2@example.com',
        'channel_id' => $channel->id,
    ]);

    $response->assertStatus(201);

    $invitedUser = User::where('email', 'newuser2@example.com')->first();
    expect($channel->isMember($invitedUser))->toBeTrue();
});

it('resends email on subsequent invite without creating duplicate user', function () {
    Mail::fake();
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/invitations', ['email' => 'duplicate@example.com'])->assertStatus(201);

    // 2nd time
    $response = $this->postJson('/api/invitations', ['email' => 'duplicate@example.com']);
    $response->assertStatus(200);

    expect(User::where('email', 'duplicate@example.com')->count())->toBe(1);
    Mail::assertQueued(UserInvitedMail::class, 2);
});

it('does not send email for existing active user', function () {
    Mail::fake();
    $user = User::factory()->create();
    $existingUser = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/invitations', [
        'email' => $existingUser->email,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('is_pending', false);

    Mail::assertNotQueued(UserInvitedMail::class);
});
