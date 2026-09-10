<?php

namespace App\Actions\Encounters;

use App\Enums\EncounterStatus;
use App\Models\Combatant;
use App\Models\Encounter;
use Illuminate\Support\Facades\DB;

class DuplicateEncounter
{
    /**
     * The same fight again, before anybody rolled.
     *
     * A GM builds an ambush for the road, the party takes the river instead, and the
     * fight is wanted three sessions later with everything still standing. This is
     * that, and it is deliberately not an encounter library: a copy costs one button
     * and no table, and the browser pass will say whether a library is worth building.
     *
     * What comes across is the fight as it was assembled: names, hit points, armour
     * class, initiative bonus, the links back to an entity and a stat block, the
     * legendary count, the lair action, and the order the rows are in.
     *
     * What does not come across is everything that happened once it started. No
     * initiative, no round, no turn marker, no damage, no conditions, no concentration
     * and no death saves. A copy is the fight before it started, so hit points go back
     * to the maximum the row was built with.
     *
     * No broadcast: the new fight is not the one anybody has open.
     */
    public function handle(Encounter $encounter, ?string $name = null): Encounter
    {
        return DB::transaction(function () use ($encounter, $name): Encounter {
            $copy = Encounter::create([
                'campaign_id' => $encounter->campaign_id,
                'game_session_id' => null,
                'name' => $name === null || trim($name) === ''
                    ? $encounter->name.' (copy)'
                    : trim($name),
                'status' => EncounterStatus::Planning,
                'round' => 0,
                'lair_action_note' => $encounter->lair_action_note,
                'lair_initiative' => $encounter->lair_initiative,
                'active_combatant_id' => null,
                'created_by' => auth()->id(),
            ]);

            foreach ($encounter->combatants()->get() as $combatant) {
                Combatant::create([
                    'campaign_id' => $copy->campaign_id,
                    'encounter_id' => $copy->id,
                    'entity_id' => $combatant->entity_id,
                    'stat_block_id' => $combatant->stat_block_id,
                    'name' => $combatant->name,
                    'initiative' => null,
                    'initiative_bonus' => $combatant->initiative_bonus,
                    'hp' => $combatant->max_hp ?? $combatant->hp,
                    'max_hp' => $combatant->max_hp,
                    'ac' => $combatant->ac,
                    'conditions' => [],
                    'concentrating_on' => null,
                    'death_save_successes' => 0,
                    'death_save_failures' => 0,
                    'legendary_actions_max' => $combatant->legendary_actions_max,
                    'legendary_actions_left' => $combatant->legendary_actions_max,
                    'position' => $combatant->position,
                    'player_visible' => $combatant->player_visible,
                ]);
            }

            return $copy;
        });
    }
}
