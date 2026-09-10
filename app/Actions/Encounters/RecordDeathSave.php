<?php

namespace App\Actions\Encounters;

use App\Events\EncounterChanged;
use App\Models\Combatant;

class RecordDeathSave
{
    /**
     * One mark, success or failure, on a row that is on nought.
     *
     * A save on a standing combatant is a misclick rather than a rule, so it writes
     * nothing. Both counts stop at three: the third is the answer, and a fourth would
     * be a number the mechanic has no meaning for.
     */
    public function success(Combatant $combatant): void
    {
        $this->record($combatant, 'death_save_successes');
    }

    public function failure(Combatant $combatant): void
    {
        $this->record($combatant, 'death_save_failures');
    }

    /**
     * Back to no marks at all. The GM's undo, and what healing above nought does on
     * its own through ApplyDamage.
     */
    public function clear(Combatant $combatant): void
    {
        $combatant->update([
            'death_save_successes' => 0,
            'death_save_failures' => 0,
        ]);

        EncounterChanged::dispatch($combatant->campaign_id, $combatant->encounter_id);
    }

    private function record(Combatant $combatant, string $column): void
    {
        if (! $combatant->isDown()) {
            return;
        }

        $combatant->update([
            $column => min(Combatant::DEATH_SAVES, $combatant->{$column} + 1),
        ]);

        EncounterChanged::dispatch($combatant->campaign_id, $combatant->encounter_id);
    }
}
