<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\MentionEmailDigest;
use Illuminate\Support\Facades\Cache;

/**
 * Debounce email-сповіщень про згадки (фаза B3): перша згадка офлайн-
 * користувача атомарно (Cache::add) відкриває вікно і ставить у чергу
 * один відкладений дайджест; наступні згадки у межах вікна нових
 * листів не породжують — їх охопить уже запланований дайджест.
 */
class MentionDigestNotifier
{
    public const DEBOUNCE_SECONDS = 120;

    public function __construct(private Presence $presence) {}

    public function notify(User $user): void
    {
        if ($this->presence->isOnline($user)) {
            return;
        }

        if (! Cache::add("mention-digest:{$user->id}", true, self::DEBOUNCE_SECONDS)) {
            return;
        }

        $user->notify(
            (new MentionEmailDigest(now()))->delay(now()->addSeconds(self::DEBOUNCE_SECONDS)),
        );
    }
}
