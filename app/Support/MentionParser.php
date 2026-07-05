<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Витягання згадок @user із тексту (фаза B3): кандидати — члени каналу,
 * збіг — входження "@ім'я" без урахування регістру. Пошук підрядком,
 * без regex по користувацькому вводу — довільні спецсимволи/ін'єкції
 * в тілі повідомлення парсер не ламають.
 */
class MentionParser
{
    /**
     * @param  Collection<int, User>  $candidates
     * @return Collection<int, User>
     */
    public static function mentioned(string $body, Collection $candidates): Collection
    {
        $haystack = Str::lower($body);

        return $candidates
            ->filter(fn (User $user): bool => str_contains($haystack, '@'.Str::lower($user->name)))
            ->values();
    }
}
