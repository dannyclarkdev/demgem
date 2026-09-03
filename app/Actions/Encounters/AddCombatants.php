<?php

namespace App\Actions\Encounters;

use App\Events\EncounterChanged;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AddCombatants
{
    public const MAX_QUANTITY = 20;

    /**
     * Adds rows to the end of the turn order.
     *
     * Name and stats are copied rather than read through entity_id: there are no stat
     * blocks until the compendium lands, so HP and AC are typed once on the add form
     * and applied to every copy, and a deleted NPC still leaves a complete row.
     *
     * A quantity above one numbers them, "Goblin 1" through "Goblin 4", which is how a
     * GM refers to them out loud.
     *
     * @return Collection<int, Combatant>
     */
    public function handle(
        Encounter $encounter,
        string $name,
        int $quantity = 1,
        ?Entity $entity = null,
        ?int $hp = null,
        ?int $ac = null,
        ?int $initiativeBonus = null,
    ): Collection {
        $combatants = $this->create($encounter, $name, $quantity, $entity, $hp, $ac, $initiativeBonus);

        EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);

        return $combatants;
    }

    /**
     * The rows themselves, with no broadcast. Callers that add more than one group
     * dispatch once when they are done, so a five-person party is one event and not
     * five re-renders on every open screen.
     *
     * @return Collection<int, Combatant>
     */
    private function create(
        Encounter $encounter,
        string $name,
        int $quantity = 1,
        ?Entity $entity = null,
        ?int $hp = null,
        ?int $ac = null,
        ?int $initiativeBonus = null,
    ): Collection {
        $quantity = max(1, min($quantity, self::MAX_QUANTITY));
        $name = trim($name);

        return DB::transaction(function () use ($encounter, $name, $quantity, $entity, $hp, $ac, $initiativeBonus): Collection {
            $position = $this->nextPosition($encounter);
            $added = new Collection;

            for ($copy = 1; $copy <= $quantity; $copy++) {
                $added->push(Combatant::create([
                    'campaign_id' => $encounter->campaign_id,
                    'encounter_id' => $encounter->id,
                    'entity_id' => $entity?->id,
                    'name' => $quantity > 1 ? "{$name} {$copy}" : $name,
                    'initiative' => null,
                    'initiative_bonus' => $initiativeBonus,
                    'hp' => $hp,
                    'max_hp' => $hp,
                    'ac' => $ac,
                    'conditions' => [],
                    'position' => $position++,
                ]));
            }

            return $added;
        });
    }

    /**
     * One row per entity, named after it. Used by "Add the party" and by the one-click
     * add from a session's Monsters bucket.
     *
     * @param  Collection<int, Entity>  $entities
     * @return Collection<int, Combatant>
     */
    public function fromEntities(Encounter $encounter, Collection $entities): Collection
    {
        $added = new Collection;

        foreach ($entities as $entity) {
            $added = $added->concat($this->create($encounter, $entity->name, 1, $entity));
        }

        EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);

        return $added;
    }

    private function nextPosition(Encounter $encounter): int
    {
        $max = $encounter->combatants()->max('position');

        return $max === null ? 0 : ((int) $max) + 1;
    }
}
