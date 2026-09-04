<?php

namespace App\Actions\Clocks;

use App\Events\ClockChanged;
use App\Models\Clock;

class DeleteClock
{
    /**
     * Takes the dial away. The entity it was about is untouched: a clock is a note
     * about how close a thing is, not the thing.
     */
    public function handle(Clock $clock): void
    {
        $campaignId = $clock->campaign_id;
        $id = $clock->id;

        $clock->delete();

        ClockChanged::dispatch($campaignId, $id);
    }
}
