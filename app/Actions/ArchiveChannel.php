<?php

namespace App\Actions;

use App\Models\Channel;

class ArchiveChannel
{
    /**
     * Ідемпотентно: повторна архівація не змінює первинний archived_at.
     */
    public function handle(Channel $channel): Channel
    {
        if (! $channel->isArchived()) {
            $channel->update(['archived_at' => now()]);
        }

        return $channel;
    }
}
