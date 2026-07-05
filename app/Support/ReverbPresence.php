<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Throwable;

/**
 * Presence через Reverb (Pusher HTTP API): фронт тримає підписку на
 * глобальний presence-online, бекенд питає список його учасників.
 * Reverb недоступний або відповідь неочікувана → вважаємо офлайн
 * (безпечний дефолт: сповіщення краще надіслати, ніж загубити).
 */
class ReverbPresence implements Presence
{
    public function isOnline(User $user): bool
    {
        $broadcaster = Broadcast::connection();

        if (! $broadcaster instanceof PusherBroadcaster) {
            return false;
        }

        try {
            $response = $broadcaster->getPusher()->get('/channels/presence-online/users');

            /** @var list<object{id: string|int}> $members */
            $members = is_object($response) && is_array($response->users ?? null) ? $response->users : [];

            foreach ($members as $member) {
                if ((string) $member->id === (string) $user->id) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }
}
