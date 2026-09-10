<?php

namespace App\Actions\Compendium;

use App\Models\Campaign;
use App\Models\StatBlock;
use Illuminate\Support\Str;

/**
 * A slug that means one creature.
 *
 * The database keeps a campaign's own slugs distinct from each other, and keeps the
 * shipped ones distinct from each other, but it does not stop a GM's "Goblin Warrior"
 * from taking the slug a shipped goblin warrior already has. Nothing would break if it
 * did, but a slug that names two rows makes every URL and every API call ambiguous, so
 * a collision is suffixed here instead: goblin-warrior-2.
 *
 * A name with nothing sluggable in it still gets a slug, because a row addressed by
 * nothing cannot be opened.
 */
class StatBlockSlug
{
    public const MAX_LENGTH = 160;

    private const MAX_ATTEMPTS = 200;

    public function for(Campaign $campaign, string $name, ?string $ignoreId = null): string
    {
        $base = Str::of($name)->slug()->limit(self::MAX_LENGTH - 8, '')->value();

        if ($base === '') {
            $base = 'creature';
        }

        $taken = $this->taken($campaign, $ignoreId);

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        for ($suffix = 2; $suffix <= self::MAX_ATTEMPTS; $suffix++) {
            $candidate = "{$base}-{$suffix}";

            if (! in_array($candidate, $taken, true)) {
                return $candidate;
            }
        }

        return $base.'-'.Str::lower(Str::random(6));
    }

    /**
     * Every slug this campaign could confuse the new one with: its own, and the shipped
     * ones in its ruleset when it has any.
     *
     * @return list<string>
     */
    private function taken(Campaign $campaign, ?string $ignoreId): array
    {
        return StatBlock::query()
            ->forCampaign($campaign)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->pluck('slug')
            ->all();
    }
}
