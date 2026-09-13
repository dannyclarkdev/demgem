<?php

namespace App\Actions\Characters;

use App\Models\CharacterSheet;
use App\Models\Entity;

class SaveSheet
{
    /**
     * Creates or replaces the character's one sheet from validated parts. The
     * component checked every number against the bounds before it got here.
     *
     * @param  array<string, mixed>  $parts
     */
    public function handle(Entity $character, array $parts): CharacterSheet
    {
        /** @var CharacterSheet $sheet */
        $sheet = CharacterSheet::query()->updateOrCreate(
            ['entity_id' => $character->id],
            [...$parts, 'campaign_id' => $character->campaign_id],
        );

        return $sheet;
    }
}
