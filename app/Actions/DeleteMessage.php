<?php

namespace App\Actions;

use App\Events\MessageDeleted;
use App\Models\Message;

class DeleteMessage
{
    /**
     * Soft delete — history залишається в БД, deleted_at у контракті.
     */
    public function handle(Message $message): void
    {
        $message->delete();

        MessageDeleted::dispatch($message);
    }
}
