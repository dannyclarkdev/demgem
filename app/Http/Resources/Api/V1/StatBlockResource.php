<?php

namespace App\Http\Resources\Api\V1;

use App\Models\StatBlock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A creature as JSON.
 *
 * source, license and attribution are on every document rather than on the collection
 * envelope: a script that pulls one stat block owes the same credit as one that pulls
 * the list, and a key that can only reach one document must still receive the notice.
 *
 * @mixin StatBlock
 */
class StatBlockResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'ruleset' => $this->ruleset,
            'type_line' => $this->type_line,
            'is_swarm' => $this->is_swarm,
            'size' => $this->size,
            'creature_type' => $this->creature_type,
            'subtype' => $this->subtype,
            'alignment' => $this->alignment,
            'ac' => $this->ac,
            'initiative_bonus' => $this->initiative_bonus,
            'hp' => $this->hp,
            'hit_dice' => $this->hit_dice,
            'speed' => $this->speed,
            'ability_scores' => $this->ability_scores,
            'skills' => $this->skills,
            'senses' => $this->senses,
            'languages' => $this->languages,
            'gear' => $this->gear,
            'resistances' => $this->resistances,
            'immunities' => $this->immunities,
            'vulnerabilities' => $this->vulnerabilities,
            'cr' => $this->cr,
            'cr_value' => $this->cr_value,
            'xp' => $this->xp,
            'cr_note' => $this->cr_note,
            'traits' => $this->traits,
            'actions' => $this->actions,
            'bonus_actions' => $this->bonus_actions,
            'reactions' => $this->reactions,
            'legendary_actions' => $this->legendary_actions,
            'source' => $this->source,
            'license' => $this->license,
            'attribution' => config('compendium.attribution'),
        ];
    }
}
