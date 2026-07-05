<?php

namespace App\Actions;

use App\Events\ChannelUpdated;
use App\Models\Channel;

class ArchiveChannel
{
    /**
     * Ідемпотентно: повторна архівація не змінює первинний archived_at
     * і не генерує повторної broadcast-події.
     */
    public function handle(Channel $channel): Channel
    {
        if (! $channel->isArchived()) {
            $channel->update(['archived_at' => now()]);

            ChannelUpdated::dispatch($channel);
        }

        return $channel;
    }
}
