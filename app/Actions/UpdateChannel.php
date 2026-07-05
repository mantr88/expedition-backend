<?php

namespace App\Actions;

use App\Models\Channel;

class UpdateChannel
{
    /**
     * @param  array{name?: string, topic?: string|null}  $attributes
     */
    public function handle(Channel $channel, array $attributes): Channel
    {
        $channel->update($attributes);

        return $channel;
    }
}
