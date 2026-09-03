<?php

namespace App\Actions\Maps;

use App\Events\MapChanged;
use App\Models\MapMarker;

class RemoveMarker
{
    /**
     * Takes a pin off the map. The entity it pointed at is untouched: a pin is a note
     * about where a thing is, not the thing.
     */
    public function handle(MapMarker $marker): void
    {
        $marker->delete();

        MapChanged::dispatch($marker->campaign_id, $marker->entity_id);
    }
}
