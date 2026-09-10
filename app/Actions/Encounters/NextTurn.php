<?php

namespace App\Actions\Encounters;

use App\Enums\EncounterStatus;
use App\Events\EncounterChanged;
use App\Models\Encounter;

class NextTurn
{
    public function __construct(private readonly SpendLegendaryAction $legendary) {}

    /**
     * Advances the turn marker by position. A wrap past the end starts a new round.
     *
     * The marker is stored as an id rather than an index, because positions get
     * rewritten on every reorder and an index would silently point at somebody else.
     * An id that no longer resolves means the active combatant was removed, so the
     * order starts again from the top.
     *
     * A creature with legendary actions gets them back when the marker reaches it,
     * which is where the refill belongs: it is the turn arriving that returns them,
     * not a button somebody at the table has to remember to press.
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

            $this->refillLegendaryActions($encounter, $ids[0]);

            EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);

            return;
        }

        $next = $current + 1;
        $wrapped = $next >= count($ids);

        $active = $wrapped ? $ids[0] : $ids[$next];

        $encounter->update([
            'active_combatant_id' => $active,
            'status' => EncounterStatus::Active,
            'round' => $wrapped ? $encounter->round + 1 : max(1, $encounter->round),
        ]);

        $this->refillLegendaryActions($encounter, $active);

        EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);
    }

    /**
     * The row whose turn it now is gets its legendary actions back, if it has any.
     * Almost no row does, so this costs one query on a fight with no legendary
     * creature in it and writes nothing.
     */
    private function refillLegendaryActions(Encounter $encounter, string $combatantId): void
    {
        $combatant = $encounter->combatants()
            ->whereKey($combatantId)
            ->whereNotNull('legendary_actions_max')
            ->first();

        if ($combatant !== null) {
            $this->legendary->refill($combatant);
        }
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
