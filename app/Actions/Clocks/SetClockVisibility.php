<?php

namespace App\Actions\Clocks;

use App\Events\ClockChanged;
use App\Models\Clock;

class SetClockVisibility
{
    /**
     * Shows or hides one dial on the party's screen.
     *
     * Revealing a clock does not reveal what it is about. The link has a gate of its
     * own, so "The Duke's suspicion" can tick in front of the party while the duke's
     * page stays shut. That is the difference from a map pin, which is nothing but a
     * link and so disappears entirely when its target does.
     */
    public function handle(Clock $clock, bool $visible): void
    {
        if ($clock->player_visible === $visible) {
            return;
        }

        $clock->update(['player_visible' => $visible]);

        ClockChanged::dispatch($clock->campaign_id, $clock->id);
    }

    public function toggle(Clock $clock): void
    {
        $this->handle($clock, ! $clock->player_visible);
    }
}
