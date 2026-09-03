<?php

namespace App\Actions\Encounters;

use App\Models\Combatant;

class ApplyDamage
{
    /**
     * One signed amount: positive damages, negative heals.
     *
     * HP clamps at 0 and at max_hp when one is set. Death saves and negative hit points
     * are ruleset features and belong to P2; the column is signed so they have room.
     */
    public function handle(Combatant $combatant, int $amount): void
    {
        if ($combatant->hp === null) {
            return;
        }

        $hp = $combatant->hp - $amount;
        $hp = max(0, $hp);

        if ($combatant->max_hp !== null) {
            $hp = min($hp, $combatant->max_hp);
        }

        $combatant->update(['hp' => $hp]);
    }
}
