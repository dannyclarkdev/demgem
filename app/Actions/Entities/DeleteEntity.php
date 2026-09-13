<?php

namespace App\Actions\Entities;

use App\Models\Campaign;
use App\Models\Entity;
use Illuminate\Support\Facades\DB;

class DeleteEntity
{
    /**
     * Soft deletes. Children move up to the deleted entity's parent so nothing vanishes with it.
     *
     * An arc's quests and sessions are unfiled rather than moved: an arc has no parent
     * to move them to, and the column is nullOnDelete for the hard delete the soft
     * one never triggers.
     */
    public function handle(Entity $entity): void
    {
        DB::transaction(function () use ($entity): void {
            $entity->children()->update(['parent_id' => $entity->parent_id]);

            if ($entity->isArc()) {
                $entity->questsInArc()->update(['arc_id' => null]);
                $entity->sessionsInArc()->update(['arc_id' => null]);
            }

            // The screen on the wall. nullOnDelete never fires for a soft delete, so the
            // columns are cleared here, the way an arc's quests are unfiled above.
            Campaign::query()
                ->whereKey($entity->campaign_id)
                ->where('screen_entity_id', $entity->id)
                ->update(['screen_focus' => null, 'screen_entity_id' => null]);

            $entity->delete();
        });
    }
}
