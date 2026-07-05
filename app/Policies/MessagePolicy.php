<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

/**
 * Редагувати може лише автор; видаляти — автор або owner/admin каналу.
 * Право надсилання перевіряється через ChannelPolicy::post.
 */
class MessagePolicy
{
    public function update(User $user, Message $message): bool
    {
        return $message->user_id === $user->id;
    }

    public function delete(User $user, Message $message): bool
    {
        if ($message->user_id === $user->id) {
            return true;
        }

        $membership = $message->channel->membershipFor($user);

        return $membership !== null && in_array($membership->role, ['owner', 'admin'], true);
    }
}
