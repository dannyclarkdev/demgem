<?php

namespace App\Actions\Encounters;

use App\Actions\Dice\RollDice;
use App\Events\EncounterChanged;
use App\Models\Combatant;
use App\Models\Encounter;

class RollInitiative
{
    public function __construct(private readonly RollDice $rollDice) {}

    /**
     * Fills initiative for every combatant the GM runs, which is everyone except the
     * player characters: their players roll their own.
     *
     * Nothing is written to the dice log. Twelve lines for one button is noise, not
     * history, so this uses the roller without the logging path.
     */
    public function handle(Encounter $encounter): int
    {
        $rolled = 0;

        foreach ($encounter->combatants()->with('entity')->get() as $combatant) {
            if ($combatant->isPlayerCharacter()) {
                continue;
            }

            $this->rollFor($combatant);
            $rolled++;
        }

        if ($rolled > 0) {
            EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);
        }

        return $rolled;
    }

    public function rollFor(Combatant $combatant): int
    {
        $bonus = $combatant->initiative_bonus ?? 0;
        $formula = $bonus === 0 ? '1d20' : '1d20'.($bonus > 0 ? '+' : '-').abs($bonus);

        $total = $this->rollDice->roll($formula)->total;

        $combatant->update(['initiative' => $total]);

        return $total;
    }
}
