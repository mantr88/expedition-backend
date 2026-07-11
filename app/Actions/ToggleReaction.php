<?php

namespace App\Actions;

use App\Events\ReactionToggled;
use App\Models\Message;
use App\Models\Reaction;
use App\Models\User;

class ToggleReaction
{
    /**
     * Toggle: якщо реакція існує — видаляє, інакше створює.
     *
     * @return array{action: string, count: int}
     */
    public function handle(User $user, Message $message, string $emoji): array
    {
        $existing = Reaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();

        if ($existing !== null) {
            $existing->delete();
            $action = 'removed';
        } else {
            Reaction::create([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $emoji,
            ]);
            $action = 'added';
        }

        $count = Reaction::where('message_id', $message->id)->where('emoji', $emoji)->count();

        ReactionToggled::dispatch($message, $emoji, $count, $user->id, $action);

        return ['action' => $action, 'count' => $count];
    }
}
