<?php

namespace App\Actions\Entities;

use App\Models\EntityRelation;

class SetRelationVisibility
{
    /**
     * The eye. It is the first of three gates, and the only one the GM turns.
     */
    public function handle(EntityRelation $relation, bool $visible): void
    {
        $relation->update(['player_visible' => $visible]);
    }
}
