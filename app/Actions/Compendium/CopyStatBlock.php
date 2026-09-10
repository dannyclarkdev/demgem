<?php

namespace App\Actions\Compendium;

use App\Models\Campaign;
use App\Models\StatBlock;

class CopyStatBlock
{
    public function __construct(private readonly StatBlockSlug $slugs) {}

    /**
     * A creature into the campaign, so the GM can change it.
     *
     * The fastest way to a homebrew ogre is an ogre, which is why this exists at all:
     * an empty thirty-field form is a worse starting point than a filled one.
     *
     * source and license come across untouched. Copying CC BY material into a campaign
     * is what the licence allows, and carrying the notice with the words is the thing
     * it asks for in return — a copy that dropped them would put licensed prose in a
     * GM's export with nothing attached saying where it came from.
     *
     * A copy of the campaign's own creature is the plain duplicate a GM reaches for
     * when two monsters are nearly the same, and it keeps that campaign's own source.
     */
    public function handle(Campaign $campaign, StatBlock $statBlock, ?string $name = null): StatBlock
    {
        $name = trim((string) ($name ?? $statBlock->name));
        $name = $name === '' ? $statBlock->name : $name;

        $attributes = array_intersect_key($statBlock->attributesToArray(), array_flip(StatBlock::WRITABLE));

        return StatBlock::create([
            ...$attributes,
            'campaign_id' => $campaign->id,
            'ruleset' => $campaign->ruleset->value,
            'slug' => $this->slugs->for($campaign, $name),
            'name' => $name,
            'source' => $statBlock->source,
            'license' => $statBlock->license,
        ]);
    }
}
