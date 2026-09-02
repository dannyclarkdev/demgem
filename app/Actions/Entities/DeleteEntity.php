<?php

namespace App\Actions\Entities;

use App\Models\Entity;
use Illuminate\Support\Facades\DB;

class DeleteEntity
{
    /**
     * Soft deletes. Children move up to the deleted entity's parent so nothing vanishes with it.
     */
    public function handle(Entity $entity): void
    {
        DB::transaction(function () use ($entity): void {
            $entity->children()->update(['parent_id' => $entity->parent_id]);
            $entity->delete();
        });
    }
}
