<?php

namespace App\Actions\Entities;

use App\Models\Entity;
use App\Models\EntityRelation;

class RelateEntities
{
    /**
     * Writes one row from $source's side. The caller has already checked that the
     * target is in the same campaign and is not the source itself.
     */
    public function handle(Entity $source, Entity $target, string $label, ?string $reverseLabel, bool $playerVisible = false): EntityRelation
    {
        $position = $source->relations()->exists() ? (int) $source->relations()->max('position') + 1 : 0;

        return $source->relations()->create([
            'campaign_id' => $source->campaign_id,
            'target_entity_id' => $target->id,
            'label' => $label,
            'reverse_label' => $reverseLabel,
            'player_visible' => $playerVisible,
            'position' => $position,
        ]);
    }
}
