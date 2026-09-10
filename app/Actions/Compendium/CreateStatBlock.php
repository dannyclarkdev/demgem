<?php

namespace App\Actions\Compendium;

use App\Models\Campaign;
use App\Models\StatBlock;
use Illuminate\Support\Str;

class CreateStatBlock
{
    public function __construct(private readonly StatBlockSlug $slugs) {}

    /**
     * A creature the GM wrote, in the campaign's own ruleset.
     *
     * Every field is optional but the name. A system-agnostic campaign writing a
     * creature with no armour class and one trait is doing the intended thing, and the
     * tracker will add it by name with nothing to copy, exactly as a typed row is added
     * today.
     *
     * The ruleset is the campaign's rather than a choice: a creature is written for the
     * game the campaign is running, and letting a GM file it under another one would
     * put it in a compendium its own campaign cannot see.
     *
     * @param  array<string, mixed>  $fields
     */
    public function handle(Campaign $campaign, array $fields): StatBlock
    {
        $attributes = $this->writable($fields);
        $name = trim((string) ($attributes['name'] ?? ''));

        return StatBlock::create([
            ...$attributes,
            'campaign_id' => $campaign->id,
            'ruleset' => $campaign->ruleset->value,
            'slug' => $this->slugs->for($campaign, $name),
            'name' => $name,
            // A GM's creature comes from their campaign and is licensed as nothing
            // public. CopyStatBlock is the one path that keeps somebody else's.
            'source' => Str::limit($campaign->name, 64, ''),
            'license' => StatBlock::OWN_LICENSE,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function writable(array $fields): array
    {
        return array_intersect_key($fields, array_flip(StatBlock::WRITABLE));
    }
}
