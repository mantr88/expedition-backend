<?php

namespace App\Actions;

use App\Mail\UserInvitedMail;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InviteUserByEmail
{
    public function __construct(
        private InviteToChannel $inviteToChannel,
    ) {}

    /**
     * Ідемпотентно: наявному користувачу акаунт не перезаписується,
     * pending-користувачу повторний виклик лише перевисилає лист.
     */
    public function handle(string $email, ?Channel $channel = null): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => Str::before($email, '@'), 'password' => null],
        );

        if ($channel !== null) {
            $this->inviteToChannel->handle($channel, $user);
        }

        if ($user->password === null) {
            $token = Password::createToken($user);
            Mail::to($user)->queue(new UserInvitedMail($user, $token));
        }

        return $user;
    }
}
