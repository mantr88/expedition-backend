<?php

namespace App\Actions;

use App\Events\ChannelUpdated;
use App\Models\Channel;

class UpdateChannel
{
    /**
     * @param  array{name?: string, topic?: string|null}  $attributes
     */
    public function handle(Channel $channel, array $attributes): Channel
    {
        $channel->update($attributes);

        ChannelUpdated::dispatch($channel);

        return $channel;
    }
}
