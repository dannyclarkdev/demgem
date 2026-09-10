<?php

namespace App\Actions\Compendium;

use App\Models\StatBlock;
use RuntimeException;

class DeleteStatBlock
{
    /**
     * A creature the campaign wrote, gone.
     *
     * Nothing cleans up after it because nothing has to: combatants.stat_block_id and
     * entities.stat_block_id are both nullOnDelete, so a fight already running keeps
     * every number it copied when the row was added and loses only the way back to the
     * prose. That is the same behaviour a shipped row dropped by a newer dataset would
     * have, and it is why AddCombatants copies rather than reads through the link.
     *
     * A shipped row is refused, as in UpdateStatBlock, and for the same reason.
     */
    public function handle(StatBlock $statBlock): void
    {
        if ($statBlock->isShipped()) {
            throw new RuntimeException('A shipped stat block is reference data and is never deleted.');
        }

        $statBlock->delete();
    }
}
