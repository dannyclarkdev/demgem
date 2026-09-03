<?php

namespace App\Actions\Encounters;

use App\Events\EncounterChanged;
use App\Models\Combatant;

class SetPlayerVisibility
{
    /**
     * Shows or hides one row on the player table view.
     *
     * It is an action rather than an update in the component, for the same reason
     * every other change to a fight is: the broadcast belongs beside the write, so
     * the encounter page, the Run screen, and any future API all move the party's
     * screens the same way.
     */
    public function handle(Combatant $combatant, bool $visible): void
    {
        if ($combatant->player_visible === $visible) {
            return;
        }

        $combatant->update(['player_visible' => $visible]);

        EncounterChanged::dispatch($combatant->campaign_id, $combatant->encounter_id);
    }

    /**
     * The eye toggle in the tracker.
     */
    public function toggle(Combatant $combatant): void
    {
        $this->handle($combatant, ! $combatant->player_visible);
    }
}
