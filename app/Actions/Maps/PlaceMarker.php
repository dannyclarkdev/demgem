<?php

namespace App\Actions\Maps;

use App\Events\MapChanged;
use App\Models\Entity;
use App\Models\MapMarker;
use Illuminate\Support\Str;

class PlaceMarker
{
    /**
     * Drops a pin at a point on the map.
     *
     * The coordinates arrive from a browser, so they arrive as a claim rather than a
     * fact, and Coordinate clamps them. The whole threat is a pin somewhere silly on
     * a map the sender can already edit, so a clamp is the right size of answer.
     *
     * The label is copied from the target rather than read through it, the way a
     * combatant's name is: a pin whose target is deleted keeps its label.
     */
    public function handle(Entity $map, float $x, float $y, ?string $label = null, ?Entity $target = null): MapMarker
    {
        $label = trim((string) ($label ?? $target->name ?? ''));

        $marker = MapMarker::create([
            'campaign_id' => $map->campaign_id,
            'entity_id' => $map->id,
            'target_entity_id' => $target?->id,
            'label' => Str::limit($label !== '' ? $label : 'Unnamed', MapMarker::MAX_LABEL_LENGTH, ''),
            'x' => Coordinate::clamp($x),
            'y' => Coordinate::clamp($y),
            // Everything the GM adds waits for the eye, exactly as a combatant does.
            'player_visible' => false,
        ]);

        MapChanged::dispatch($map->campaign_id, $map->id);

        return $marker;
    }
}
