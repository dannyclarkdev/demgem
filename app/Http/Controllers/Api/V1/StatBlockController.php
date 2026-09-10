<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\StatBlockResource;
use App\Models\Campaign;
use App\Models\StatBlock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The compendium in JSON, behind the same two gates as the screens: a GM role, and a
 * campaign whose ruleset ships a dataset.
 *
 * Read-only, and not because writing was left for later. These rows are shipped
 * reference data with a recorded provenance; a key that could edit them would make the
 * checksum in configuration a statement about nothing.
 */
class StatBlockController extends ApiController
{
    public function index(Request $request, Campaign $campaign): AnonymousResourceCollection
    {
        Gate::authorize('viewCompendium', $campaign);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:48'],
            'cr_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cr_max' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $statBlocks = StatBlock::query()
            ->forRuleset($campaign->ruleset->value)
            ->matchingName($validated['q'] ?? '')
            ->when(
                isset($validated['type']),
                fn ($query) => $query->where('creature_type', $validated['type'])
            )
            ->inChallengeRange(
                isset($validated['cr_min']) ? (float) $validated['cr_min'] : null,
                isset($validated['cr_max']) ? (float) $validated['cr_max'] : null,
            )
            ->inReadingOrder()
            ->paginate(50)
            ->withQueryString();

        return StatBlockResource::collection($statBlocks);
    }

    /**
     * By slug, unlike an entity. A stat block is not a GM's to rename, so its slug is
     * stable in a way an entity's is not, and a script that stores one keeps working.
     */
    public function show(Campaign $campaign, string $slug): StatBlockResource
    {
        Gate::authorize('viewCompendium', $campaign);

        $statBlock = StatBlock::query()
            ->forRuleset($campaign->ruleset->value)
            ->where('slug', $slug)
            ->firstOrFail();

        return new StatBlockResource($statBlock);
    }
}
