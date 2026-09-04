<?php

namespace App\Actions\Clocks;

use App\Events\ClockChanged;
use App\Models\Campaign;
use App\Models\Clock;
use App\Models\Entity;
use Illuminate\Support\Str;

class CreateClock
{
    /**
     * A new dial, empty and hidden.
     *
     * Hidden is the default the column carries, for the reason a new pin and a new
     * combatant are hidden: a GM builds these during prep, and the party learns that
     * something is counting when the GM decides they do.
     *
     * A new clock goes to the end of the list, so making one never moves the others.
     */
    public function handle(Campaign $campaign, string $name, int $segments = Clock::DEFAULT_SEGMENTS, ?Entity $about = null): Clock
    {
        $clock = Clock::create([
            'campaign_id' => $campaign->id,
            'entity_id' => $about?->id,
            'name' => Str::limit(trim($name), Clock::MAX_NAME_LENGTH, ''),
            'segments' => Segments::clamp($segments),
            'filled' => 0,
            'player_visible' => false,
            'position' => (int) Clock::query()->where('campaign_id', $campaign->id)->max('position') + 1,
        ]);

        ClockChanged::dispatch($campaign->id, $clock->id);

        return $clock;
    }
}
