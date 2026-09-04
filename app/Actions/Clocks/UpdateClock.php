<?php

namespace App\Actions\Clocks;

use App\Events\ClockChanged;
use App\Models\Clock;
use App\Models\Entity;
use Illuminate\Support\Str;

class UpdateClock
{
    /**
     * Renames a clock, resizes it, or points it at something.
     *
     * Shrinking a dial below its fill would leave a clock reading "8 of 6", so the
     * fill comes down with it. Growing one leaves the fill where it is, which is what
     * a GM means by "this is going to take longer than I thought".
     */
    public function handle(Clock $clock, string $name, int $segments, ?Entity $about = null): void
    {
        $segments = Segments::clamp($segments);

        $clock->update([
            'name' => Str::limit(trim($name), Clock::MAX_NAME_LENGTH, ''),
            'segments' => $segments,
            'filled' => Segments::clampFill($clock->filled, $segments),
            'entity_id' => $about?->id,
        ]);

        ClockChanged::dispatch($clock->campaign_id, $clock->id);
    }
}
