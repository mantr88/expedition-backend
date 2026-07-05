<?php

namespace App\Actions;

use App\Events\MessageUpdated;
use App\Models\Message;

class EditMessage
{
    public function handle(Message $message, string $body): Message
    {
        $message->update([
            'body' => $body,
            'edited_at' => now(),
        ]);

        MessageUpdated::dispatch($message);

        return $message;
    }
}
