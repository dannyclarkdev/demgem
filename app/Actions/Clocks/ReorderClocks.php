<?php

namespace App\Actions\Clocks;

use App\Actions\Support\ReorderPositions;
use App\Events\ClockChanged;
use App\Models\Campaign;
use App\Models\Clock;
use Illuminate\Database\Eloquent\Builder;

class ReorderClocks
{
    public function __construct(private readonly ReorderPositions $reorderPositions) {}

    /**
     * The GM's order is "what matters tonight", so it is worth dragging. This goes
     * through the one reorder path, as scenes, objectives, combatants, and table rows
     * all do, so the behaviour cannot drift between them.
     */
    public function handle(Campaign $campaign, string $clockId, int $position): void
    {
        $this->reorderPositions->handle($this->ordered($campaign), $clockId, $position);

        ClockChanged::dispatch($campaign->id, $clockId);
    }

    /**
     * One step up or down, for a keyboard and a tablet.
     *
     * The step is measured from the row's place in the list rather than from its
     * stored position. A delete leaves a hole in the numbers until the next reorder
     * rewrites them, and a hole would make "move up" jump two rows or none.
     */
    public function move(Campaign $campaign, Clock $clock, int $offset): void
    {
        $ids = $this->ordered($campaign)->pluck('id')->all();
        $index = array_search($clock->id, $ids, true);

        if ($index === false) {
            return;
        }

        $this->handle($campaign, $clock->id, $index + $offset);
    }

    /**
     * @return Builder<Clock>
     */
    private function ordered(Campaign $campaign): Builder
    {
        return Clock::query()->where('campaign_id', $campaign->id)->orderBy('position');
    }
}
