<?php

namespace App\Actions\Clocks;

use App\Events\ClockChanged;
use App\Models\Clock;

class TickClock
{
    /**
     * Moves the fill to an exact wedge. This is what a click on the dial sends, and
     * it is the reason the plus and the minus need no special case at the ends.
     */
    public function to(Clock $clock, int $filled): void
    {
        $filled = Segments::clampFill($filled, $clock->segments);

        if ($clock->filled === $filled) {
            return;
        }

        $clock->update(['filled' => $filled]);

        ClockChanged::dispatch($clock->campaign_id, $clock->id);
    }

    /**
     * A wedge up or a wedge down. A countdown is this with a negative delta, which is
     * the whole reason the table has no direction column.
     */
    public function by(Clock $clock, int $delta): void
    {
        $this->to($clock, $clock->filled + $delta);
    }
}
