<?php

namespace App\Actions\Encounters;

use App\Events\EncounterChanged;
use App\Models\Combatant;
use Illuminate\Support\Str;

class SetConcentration
{
    /**
     * What this row is holding, or nothing at all.
     *
     * One effect per combatant, which is the rule and also the reason this is a column
     * rather than a list. An empty string clears it, so the same call both sets and
     * drops and there is no second path to keep in step.
     */
    public function handle(Combatant $combatant, ?string $effect): void
    {
        $effect = $effect === null ? null : trim($effect);

        $combatant->update([
            'concentrating_on' => $effect === null || $effect === ''
                ? null
                : Str::limit($effect, Combatant::MAX_CONCENTRATION_LENGTH, ''),
        ]);

        EncounterChanged::dispatch($combatant->campaign_id, $combatant->encounter_id);
    }

    public function clear(Combatant $combatant): void
    {
        $this->handle($combatant, null);
    }
}
