<?php

namespace App\Actions\Encounters;

use App\Models\Combatant;
use Illuminate\Support\Facades\DB;

class RemoveCombatant
{
    /**
     * Deletes the row and clears the turn marker when it pointed here.
     *
     * encounters.active_combatant_id carries no foreign key, because a constraint back
     * to combatants would be circular, so this is the cleanup the database will not do.
     */
    public function handle(Combatant $combatant): void
    {
        DB::transaction(function () use ($combatant): void {
            $encounter = $combatant->encounter;

            if ($encounter->active_combatant_id === $combatant->id) {
                $encounter->update(['active_combatant_id' => null]);
            }

            $combatant->delete();
        });
    }
}
