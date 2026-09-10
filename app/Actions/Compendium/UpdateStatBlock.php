<?php

namespace App\Actions\Compendium;

use App\Models\StatBlock;
use RuntimeException;

class UpdateStatBlock
{
    /**
     * The same fields on a creature the campaign already owns.
     *
     * A shipped row is refused here rather than only in a policy. The policy is the
     * gate a request goes through; this is the guarantee that no caller anywhere — a
     * seeder, a command, a future screen — writes over reference data whose checksum is
     * pinned in configuration.
     *
     * A rename does NOT move the slug. Every creature is addressed by slug, on the web
     * and in the API, and .ai/rules/api.md gives the reason a slug that moves is not an
     * address: a script that stored one stops working. An entity is addressed by id for
     * exactly that reason, and the choice here is the other one — the slug is set once
     * at creation and is stable for the life of the row, shipped or not. The cost is a
     * URL that still says harbour-thug after a rename to Dock Thug, which nobody reads.
     *
     * @param  array<string, mixed>  $fields
     */
    public function handle(StatBlock $statBlock, array $fields): StatBlock
    {
        if ($statBlock->isShipped()) {
            throw new RuntimeException('A shipped stat block is reference data and is never edited.');
        }

        $attributes = array_intersect_key($fields, array_flip(StatBlock::WRITABLE));

        if (isset($attributes['name'])) {
            $attributes['name'] = trim((string) $attributes['name']);
        }

        $statBlock->update($attributes);

        return $statBlock->refresh();
    }
}
