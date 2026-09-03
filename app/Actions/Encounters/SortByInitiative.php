<?php

namespace App\Actions\Encounters;

use App\Events\EncounterChanged;
use App\Models\Combatant;
use App\Models\Encounter;
use Illuminate\Support\Facades\DB;

class SortByInitiative
{
    /**
     * Rewrites every position from the current initiative values, highest first.
     *
     * This is a button, not a read-time sort. Ordering by initiative with position as a
     * tiebreak sounds simpler and is worse: a drag would then only mean something inside
     * a tie, which no GM will predict. Sort once, then nudge freely.
     */
    public function handle(Encounter $encounter): void
    {
        DB::transaction(function () use ($encounter): void {
            // "nulls last" is Postgres-only and SQLite would hide the difference locally,
            // so the blank-initiative rows are pushed down with a portable case expression.
            $ids = $encounter->combatants()
                ->reorder()
                ->orderByRaw('case when initiative is null then 1 else 0 end')
                ->orderByDesc('initiative')
                ->orderBy('position')
                ->pluck('id');

            foreach ($ids as $index => $id) {
                Combatant::query()->whereKey($id)->update(['position' => $index]);
            }
        });

        EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);
    }
}
