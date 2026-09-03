<?php

namespace App\Actions\Maps;

use App\Events\MapChanged;
use App\Models\Entity;
use App\Models\MapMarker;

class SetMarkerVisibility
{
    /**
     * Shows or hides one pin on the party's map.
     *
     * Revealing a pin does not reveal what it points at. That is deliberate: the
     * target's own visibility is the second gate on a player's map, so a GM who
     * reveals the pin for a GM-only NPC gets nothing rather than a leak.
     */
    public function handle(MapMarker $marker, bool $visible): void
    {
        if ($marker->player_visible === $visible) {
            return;
        }

        $marker->update(['player_visible' => $visible]);

        MapChanged::dispatch($marker->campaign_id, $marker->entity_id);
    }

    public function toggle(MapMarker $marker): void
    {
        $this->handle($marker, ! $marker->player_visible);
    }

    /**
     * The end of a session, in one click. Returns how many pins changed.
     */
    public function setAll(Entity $map, bool $visible): int
    {
        $changed = $map->markers()->where('player_visible', ! $visible)->update(['player_visible' => $visible]);

        if ($changed > 0) {
            MapChanged::dispatch($map->campaign_id, $map->id);
        }

        return $changed;
    }
}
