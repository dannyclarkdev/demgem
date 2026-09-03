<?php

namespace App\Actions\Encounters;

use App\Actions\Support\ReorderPositions;
use App\Events\EncounterChanged;
use App\Models\Combatant;
use App\Models\Encounter;

/**
 * The turn order, dragged or nudged.
 *
 * ReorderPositions does the work and is shared with scenes, objectives, and table
 * entries, so it cannot broadcast anything itself. This wraps it, which keeps the
 * rule intact: an encounter event is dispatched by an encounter action.
 */
class ReorderCombatants
{
    public function __construct(private readonly ReorderPositions $reorder) {}

    public function handle(Encounter $encounter, string $combatantId, int $position): void
    {
        $this->reorder->handle($encounter->combatants()->getQuery(), $combatantId, $position);

        EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);
    }

    public function move(Encounter $encounter, Combatant $combatant, int $offset): void
    {
        $this->reorder->move($encounter->combatants()->getQuery(), $combatant->id, $combatant->position, $offset);

        EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);
    }
}
