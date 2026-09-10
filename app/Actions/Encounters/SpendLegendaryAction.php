<?php

namespace App\Actions\Encounters;

use App\Events\EncounterChanged;
use App\Models\Combatant;

class SpendLegendaryAction
{
    /**
     * One use gone. Stops at nought rather than going negative, because a creature
     * with none left is a rule the GM is reading off the row, not a running total.
     *
     * They come back on the creature's own turn, which NextTurn does: the refill
     * belongs to the turn marker moving, not to a button somebody has to remember.
     */
    public function spend(Combatant $combatant): void
    {
        if (! $combatant->hasLegendaryActions()) {
            return;
        }

        $combatant->update([
            'legendary_actions_left' => max(0, ($combatant->legendary_actions_left ?? 0) - 1),
        ]);

        EncounterChanged::dispatch($combatant->campaign_id, $combatant->encounter_id);
    }

    /**
     * The GM's own count, for a creature the dataset did not price or a fight in a
     * lair. Zero clears the pair, because a creature with no uses has none rather than
     * having spent them.
     */
    public function setMaximum(Combatant $combatant, ?int $maximum): void
    {
        $maximum = $maximum === null
            ? null
            : max(0, min(Combatant::MAX_LEGENDARY_ACTIONS, $maximum));

        $combatant->update([
            'legendary_actions_max' => $maximum === null || $maximum === 0 ? null : $maximum,
            'legendary_actions_left' => $maximum === null || $maximum === 0 ? null : $maximum,
        ]);

        EncounterChanged::dispatch($combatant->campaign_id, $combatant->encounter_id);
    }

    /**
     * Back to full. Called when the turn marker reaches this row.
     */
    public function refill(Combatant $combatant): void
    {
        if (! $combatant->hasLegendaryActions()) {
            return;
        }

        $combatant->update(['legendary_actions_left' => $combatant->legendary_actions_max]);
    }
}
