<?php

namespace App\Actions\Entities;

use App\Models\EntityRelation;

class RemoveRelation
{
    public function handle(EntityRelation $relation): void
    {
        $relation->delete();
    }
}
