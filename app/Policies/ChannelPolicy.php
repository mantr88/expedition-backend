<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;

/**
 * Авторизація дій над каналом (фаза B1): членство, роль (owner|admin|member)
 * і тип каналу (public|private|dm). Не-член отримує 403 на читання/запис.
 */
class ChannelPolicy
{
    public function view(User $user, Channel $channel): bool
    {
        return $channel->isMember($user);
    }

    public function update(User $user, Channel $channel): bool
    {
        return $channel->type !== 'dm' && $this->manages($user, $channel);
    }

    public function archive(User $user, Channel $channel): bool
    {
        return $channel->type !== 'dm' && $this->manages($user, $channel);
    }

    public function post(User $user, Channel $channel): bool
    {
        return ! $channel->isArchived() && $channel->isMember($user);
    }

    public function join(User $user, Channel $channel): bool
    {
        return $channel->type === 'public'
            && ! $channel->isArchived()
            && ! $channel->isMember($user);
    }

    public function invite(User $user, Channel $channel): bool
    {
        if ($channel->type === 'dm' || $channel->isArchived()) {
            return false;
        }

        return $channel->type === 'public'
            ? $channel->isMember($user)
            : $this->manages($user, $channel);
    }

    public function removeMember(User $user, Channel $channel, User $member): bool
    {
        if ($channel->type === 'dm') {
            return false;
        }

        if ($user->is($member)) {
            return $channel->isMember($user);
        }

        $target = $channel->membershipFor($member);

        return $target !== null
            && $target->role !== 'owner'
            && $this->manages($user, $channel);
    }

    private function manages(User $user, Channel $channel): bool
    {
        $membership = $channel->membershipFor($user);

        return $membership !== null && in_array($membership->role, ['owner', 'admin'], true);
    }
}
