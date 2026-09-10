<?php

namespace App\Actions\Encounters;

use App\Actions\Dice\RollDice;
use App\Events\EncounterChanged;
use App\Exceptions\InvalidDiceFormulaException;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\StatBlock;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AddCombatants
{
    public const MAX_QUANTITY = 20;

    public function __construct(private readonly RollDice $dice) {}

    /**
     * Adds rows to the end of the turn order.
     *
     * Name and stats are copied rather than read through entity_id or stat_block_id:
     * they are what the numbers were when the row was added, so a GM who edited them
     * keeps the edit and a deleted NPC still leaves a complete row.
     *
     * A quantity above one numbers them, "Goblin 1" through "Goblin 4", which is how a
     * GM refers to them out loud.
     *
     * The party lands on the player table view straight away; everything else is
     * hidden until the GM says otherwise. See create().
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
        ?StatBlock $statBlock = null,
    ): Collection {
        $quantity = max(1, min($quantity, self::MAX_QUANTITY));
        $name = trim($name);

        return DB::transaction(function () use ($encounter, $name, $quantity, $entity, $hp, $ac, $initiativeBonus, $statBlock): Collection {
            $position = $this->nextPosition($encounter);
            $added = new Collection;

            for ($copy = 1; $copy <= $quantity; $copy++) {
                $added->push(Combatant::create([
                    'campaign_id' => $encounter->campaign_id,
                    'encounter_id' => $encounter->id,
                    'entity_id' => $entity?->id,
                    'stat_block_id' => $statBlock?->id,
                    'name' => $quantity > 1 ? "{$name} {$copy}" : $name,
                    'initiative' => null,
                    'initiative_bonus' => $initiativeBonus,
                    'hp' => $hp,
                    'max_hp' => $hp,
                    'ac' => $ac,
                    'conditions' => [],
                    // A creature the book gives legendary actions arrives with them
                    // counted. Everything else is null, which is "never had any"
                    // rather than "has spent them".
                    'legendary_actions_max' => $statBlock?->legendary_action_uses,
                    'legendary_actions_left' => $statBlock?->legendary_action_uses,
                    'position' => $position++,
                    // The party is already on the screen at the table, so a PC shows at
                    // once. Anything else the GM adds waits for the eye toggle, because
                    // an ambusher that appears on the party's screens before it appears
                    // in the fiction is the last time they trust the feature.
                    'player_visible' => $entity->is_pc ?? false,
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
        // One query for the group rather than one per entity, and strict mode refuses
        // the lazy load that reading the relation in the loop would otherwise be. The
        // parameter is a plain Collection, so wrap it: loadMissing sets the relation on
        // the same model instances either way.
        if ($entities->isNotEmpty()) {
            EloquentCollection::make($entities->all())->loadMissing('statBlock');
        }

        $added = new Collection;

        foreach ($entities as $entity) {
            // An NPC that names what it fights as arrives with those numbers, so one
            // click on a session's Monsters bucket fills the turn order properly.
            $statBlock = $entity->statBlock;

            $added = $added->concat($this->create(
                $encounter,
                $entity->name,
                1,
                $entity,
                $statBlock?->hp,
                $statBlock?->ac,
                $statBlock?->initiative_bonus,
                $statBlock,
            ));
        }

        EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);

        return $added;
    }

    /**
     * A creature from the compendium, with the numbers the book gives it.
     *
     * The prose stays in the compendium. A combatant takes the name, the hit points,
     * the armour class and the initiative bonus, plus the reference back, so nothing
     * licensed is copied onto a campaign's own rows or into its export.
     *
     * With $rollHitPoints each copy rolls the creature's hit dice, so four goblins are
     * four different totals. Without it every copy takes the average the book prints,
     * which is what a GM gets today after typing it.
     *
     * @return Collection<int, Combatant>
     */
    public function fromStatBlock(
        Encounter $encounter,
        StatBlock $statBlock,
        int $quantity = 1,
        bool $rollHitPoints = false,
    ): Collection {
        $quantity = max(1, min($quantity, self::MAX_QUANTITY));

        $added = $this->create(
            $encounter,
            $statBlock->name,
            $quantity,
            null,
            $statBlock->hp,
            $statBlock->ac,
            $statBlock->initiative_bonus,
            $statBlock,
        );

        if ($rollHitPoints && $statBlock->hit_dice !== null) {
            foreach ($added as $combatant) {
                $rolled = $this->rollHitPoints($statBlock->hit_dice);

                if ($rolled !== null) {
                    $combatant->update(['hp' => $rolled, 'max_hp' => $rolled]);
                }
            }
        }

        EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);

        return $added;
    }

    /**
     * A creature's hit dice as a total, never below one: a rolled 1 on 1d4 - 3 is still
     * something the party has to hit.
     */
    private function rollHitPoints(string $hitDice): ?int
    {
        try {
            return max(1, $this->dice->roll($hitDice)->total);
        } catch (InvalidDiceFormulaException) {
            // The dataset prints a formula this parser does not read. The average from
            // the book is already on the row, so the fight is fine without the roll.
            return null;
        }
    }

    private function nextPosition(Encounter $encounter): int
    {
        $max = $encounter->combatants()->max('position');

        return $max === null ? 0 : ((int) $max) + 1;
    }
}
