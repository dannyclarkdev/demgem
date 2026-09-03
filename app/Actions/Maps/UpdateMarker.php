<?php

namespace App\Actions\Maps;

use App\Events\MapChanged;
use App\Models\Entity;
use App\Models\MapMarker;
use Illuminate\Support\Str;

class UpdateMarker
{
    /**
     * Renames a pin, or points it somewhere else.
     *
     * Passing a target with no label copies the target's name, which is what a GM
     * means when they pick one from the autocomplete. Passing both keeps the label
     * they typed, because a pin reading "The back door" on an entity called "The Salt
     * Cathedral" is a GM being deliberate.
     */
    public function handle(MapMarker $marker, ?string $label = null, ?Entity $target = null, bool $clearTarget = false): void
    {
        $label = trim((string) ($label ?? $target->name ?? $marker->label));

        $marker->update([
            'label' => Str::limit($label !== '' ? $label : 'Unnamed', MapMarker::MAX_LABEL_LENGTH, ''),
            'target_entity_id' => $clearTarget ? null : ($target->id ?? $marker->target_entity_id),
        ]);

        MapChanged::dispatch($marker->campaign_id, $marker->entity_id);
    }
}
