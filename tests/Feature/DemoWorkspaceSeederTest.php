<?php

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Message;
use App\Models\User;

it('seeds the demo workspace with users, channels and messages', function () {
    $this->seed();

    expect(User::count())->toBe(60)
        ->and(Channel::count())->toBe(5)
        ->and(ChannelMember::count())->toBeGreaterThan(60)
        ->and(Message::count())->toBeGreaterThan(100);

    // Кожен користувач — член #general; творець каналів — owner.
    $general = Channel::where('name', 'general')->firstOrFail();
    expect($general->members()->count())->toBe(60);

    $anna = User::where('email', 'anna@example.com')->firstOrFail();
    expect($general->members()->where('user_id', $anna->id)->value('role'))->toBe('owner');
});
