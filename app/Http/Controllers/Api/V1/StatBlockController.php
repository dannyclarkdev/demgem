<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Compendium\CreateStatBlock;
use App\Actions\Compendium\UpdateStatBlock;
use App\Http\Resources\Api\V1\StatBlockResource;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\StatBlock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The compendium in JSON, behind the same gate as the screens: a GM role.
 *
 * Reads cover both halves of the book. Writes cover one: a campaign's own creatures,
 * through the same actions the form calls, which is what .ai/rules/api.md asks of every
 * write. A shipped row is refused at three separate points — the policy, the action,
 * and the query that resolves the slug — because it is reference data with a recorded
 * provenance, and a key that could edit one would make the checksum in configuration a
 * statement about nothing.
 *
 * By slug, and the slug does not move when a GM renames a creature. That is the
 * opposite call from an entity and UpdateStatBlock records why.
 */
class StatBlockController extends ApiController
{
    public function __construct(
        private readonly CreateStatBlock $creates,
        private readonly UpdateStatBlock $updates,
    ) {}

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
            ->forCampaign($campaign)
            ->matchingName($validated['q'] ?? '')
            ->when(
                isset($validated['type']),
                fn ($query) => $query->where('creature_type', $validated['type'])
            )
            ->inChallengeRange(
                isset($validated['cr_min']) ? (float) $validated['cr_min'] : null,
                isset($validated['cr_max']) ? (float) $validated['cr_max'] : null,
            )
            ->ownFirst()
            ->paginate(50)
            ->withQueryString();

        return StatBlockResource::collection($statBlocks);
    }

    /**
     * A creature the campaign writes, through the action the form calls.
     */
    public function store(Request $request, Campaign $campaign): StatBlockResource
    {
        Gate::authorize('create', [StatBlock::class, $campaign]);

        $statBlock = $this->creates->handle($campaign, $this->validated($request));

        return new StatBlockResource($statBlock);
    }

    /**
     * The same fields on a creature the campaign already owns.
     *
     * ownedBy(), not forCampaign(): a slug that names a shipped creature is a 404 here
     * rather than a 403, because a key with write access is not being told which
     * reference rows exist by the shape of the refusal.
     */
    public function update(Request $request, Campaign $campaign, string $slug): StatBlockResource
    {
        $statBlock = StatBlock::query()
            ->ownedBy($campaign)
            ->where('slug', $slug)
            ->firstOrFail();

        Gate::authorize('update', $statBlock);

        return new StatBlockResource($this->updates->handle($statBlock, $this->validated($request)));
    }

    /**
     * The same shape the editor validates, in the column names the action reads.
     *
     * ruleset, slug, campaign_id, source and license are decided by the action rather
     * than sent, so each is `prohibited` and a key that tries gets a 422 naming it —
     * the rule .ai/rules/api.md sets for a field a key may not set.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type_line' => ['nullable', 'string', 'max:160'],
            'is_swarm' => ['nullable', 'boolean'],
            'size' => ['nullable', 'string', 'max:32'],
            'creature_type' => ['nullable', 'string', 'max:48'],
            'subtype' => ['nullable', 'string', 'max:64'],
            'alignment' => ['nullable', 'string', 'max:64'],
            'ac' => ['nullable', 'integer', 'min:0', 'max:99'],
            'initiative_bonus' => ['nullable', 'integer', 'min:-20', 'max:20'],
            'hp' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'hit_dice' => ['nullable', 'string', 'max:64'],
            'speed' => ['nullable', 'string', 'max:160'],
            'ability_scores' => ['nullable', 'array'],
            'ability_scores.*.score' => ['nullable', 'integer', 'min:0', 'max:99'],
            'ability_scores.*.mod' => ['nullable', 'string', 'max:8'],
            'ability_scores.*.save' => ['nullable', 'string', 'max:8'],
            'skills' => ['nullable', 'string', 'max:255'],
            'senses' => ['nullable', 'string', 'max:255'],
            'languages' => ['nullable', 'string', 'max:255'],
            'gear' => ['nullable', 'string', 'max:255'],
            'resistances' => ['nullable', 'string', 'max:255'],
            'immunities' => ['nullable', 'string', 'max:255'],
            'vulnerabilities' => ['nullable', 'string', 'max:255'],
            'cr' => ['nullable', 'string', 'max:16'],
            'cr_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'xp' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'cr_note' => ['nullable', 'string', 'max:64'],
            'legendary_action_uses' => ['nullable', 'integer', 'min:0', 'max:'.Combatant::MAX_LEGENDARY_ACTIONS],
            'traits' => ['nullable', 'array', 'max:40'],
            'actions' => ['nullable', 'array', 'max:40'],
            'bonus_actions' => ['nullable', 'array', 'max:40'],
            'reactions' => ['nullable', 'array', 'max:40'],
            'legendary_actions' => ['nullable', 'array', 'max:40'],
            'traits.*.name' => ['nullable', 'string', 'max:120'],
            'traits.*.text' => ['required', 'string', 'max:10000'],
            'actions.*.name' => ['nullable', 'string', 'max:120'],
            'actions.*.text' => ['required', 'string', 'max:10000'],
            'bonus_actions.*.name' => ['nullable', 'string', 'max:120'],
            'bonus_actions.*.text' => ['required', 'string', 'max:10000'],
            'reactions.*.name' => ['nullable', 'string', 'max:120'],
            'reactions.*.text' => ['required', 'string', 'max:10000'],
            'legendary_actions.*.name' => ['nullable', 'string', 'max:120'],
            'legendary_actions.*.text' => ['required', 'string', 'max:10000'],
            'ruleset' => ['prohibited'],
            'slug' => ['prohibited'],
            'campaign_id' => ['prohibited'],
            'source' => ['prohibited'],
            'license' => ['prohibited'],
        ]);

        return array_intersect_key($validated, array_flip(StatBlock::WRITABLE));
    }

    /**
     * By slug, unlike an entity. A stat block is not a GM's to rename, so its slug is
     * stable in a way an entity's is not, and a script that stores one keeps working.
     */
    public function show(Campaign $campaign, string $slug): StatBlockResource
    {
        Gate::authorize('viewCompendium', $campaign);

        $statBlock = StatBlock::query()
            ->forCampaign($campaign)
            ->where('slug', $slug)
            ->firstOrFail();

        return new StatBlockResource($statBlock);
    }
}
