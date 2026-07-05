<?php

namespace App\Support;

use App\Models\User;

/**
 * Перевірка онлайн-статусу (фаза B3): онлайн-користувач отримує лише
 * realtime-подію, офлайн — email-дайджест. У тестах підміняється фейком.
 */
interface Presence
{
    public function isOnline(User $user): bool;
}
