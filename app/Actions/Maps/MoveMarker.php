<?php

namespace App\Actions\Maps;

use App\Events\MapChanged;
use App\Models\MapMarker;

class MoveMarker
{
    /**
     * Drags a pin to a new point. The same clamp as placing one, for the same reason.
     */
    public function handle(MapMarker $marker, float $x, float $y): void
    {
        $marker->update([
            'x' => Coordinate::clamp($x),
            'y' => Coordinate::clamp($y),
        ]);

        MapChanged::dispatch($marker->campaign_id, $marker->entity_id);
    }
}
