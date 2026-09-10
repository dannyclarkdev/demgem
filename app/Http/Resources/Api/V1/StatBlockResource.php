<?php

namespace App\Http\Resources\Api\V1;

use App\Models\StatBlock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A creature as JSON.
 *
 * source and license are on every document rather than on the collection envelope: a
 * script that pulls one stat block owes the same credit as one that pulls the list, and
 * a key that can only reach one document must still receive the notice.
 *
 * attribution is on the documents that owe it, which since slice 17 is not all of them.
 * `own` says which kind this is: a creature the campaign wrote carries no CC BY notice
 * because it is not CC BY material, and a copy of a shipped one carries the licence it
 * was copied from and therefore keeps the notice.
 *
 * @mixin StatBlock
 */
class StatBlockResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $document = [
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
            'legendary_action_uses' => $this->legendary_action_uses,
            'own' => ! $this->resource->isShipped(),
            'source' => $this->source,
            'license' => $this->license,
        ];

        // Shipped material only. A GM's own creature is their writing, and a notice
        // crediting the SRD under it would name the wrong author. A copy of a shipped
        // creature keeps its licence, so it keeps the notice with it.
        if ($this->license === config('compendium.license')) {
            $document['attribution'] = config('compendium.attribution');
        }

        return $document;
    }
}
