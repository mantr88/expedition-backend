<?php

namespace App\Actions;

use App\Models\Message;

class EditMessage
{
    public function handle(Message $message, string $body): Message
    {
        $message->update([
            'body' => $body,
            'edited_at' => now(),
        ]);

        return $message;
    }
}
