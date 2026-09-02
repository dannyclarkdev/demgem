<?php

namespace App\Actions\Mentions;

use App\Models\Entity;
use App\Models\Mention;

class ResolveMentionsFor
{
    /**
     * Points every unresolved mention of this entity's name at it. This is what makes
     * "create this entity" links light up everywhere at once.
     */
    public function handle(Entity $entity): int
    {
        return Mention::withoutGlobalScopes()
            ->where('campaign_id', $entity->campaign_id)
            ->whereNull('target_entity_id')
            ->whereRaw('lower(target_name) = ?', [mb_strtolower($entity->name)])
            ->where(fn ($query) => $query->whereNull('target_type')->orWhere('target_type', $entity->type->value))
            ->update(['target_entity_id' => $entity->id]);
    }
}
