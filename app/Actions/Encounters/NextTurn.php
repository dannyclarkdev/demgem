<?php

namespace App\Actions\Encounters;

use App\Enums\EncounterStatus;
use App\Events\EncounterChanged;
use App\Models\Encounter;

class NextTurn
{
    /**
     * Advances the turn marker by position. A wrap past the end starts a new round.
     *
     * The marker is stored as an id rather than an index, because positions get
     * rewritten on every reorder and an index would silently point at somebody else.
     * An id that no longer resolves means the active combatant was removed, so the
     * order starts again from the top.
     */
    public function handle(Encounter $encounter): void
    {
        $ids = $encounter->combatants()->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        $current = $encounter->active_combatant_id === null
            ? false
            : array_search($encounter->active_combatant_id, $ids, true);

        if ($current === false) {
            $encounter->update([
                'active_combatant_id' => $ids[0],
                'status' => EncounterStatus::Active,
                'round' => max(1, $encounter->round),
            ]);

            EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);

            return;
        }

        $next = $current + 1;
        $wrapped = $next >= count($ids);

        $encounter->update([
            'active_combatant_id' => $wrapped ? $ids[0] : $ids[$next],
            'status' => EncounterStatus::Active,
            'round' => $wrapped ? $encounter->round + 1 : max(1, $encounter->round),
        ]);

        EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);
    }

    /**
     * Ends the fight but keeps the round count. Every transition is legal in both
     * directions, as slice 2 decided for session status: a GM who ends a fight by
     * mistake must be able to un-end it.
     *
     * The status decides whether /table shows a fight at all, so ending one is a
     * change the party's screens must hear about, not only the GM's.
     */
    public function end(Encounter $encounter): void
    {
        $encounter->update(['status' => EncounterStatus::Done]);

        EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);
    }

    public function reopen(Encounter $encounter): void
    {
        $encounter->update(['status' => EncounterStatus::Active]);

        EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);
    }

    /**
     * Back to the top of round one, marker cleared.
     */
    public function reset(Encounter $encounter): void
    {
        $encounter->update([
            'active_combatant_id' => null,
            'round' => 0,
            'status' => EncounterStatus::Planning,
        ]);

        EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);
    }
}
