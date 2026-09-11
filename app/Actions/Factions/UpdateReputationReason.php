<?php

namespace App\Actions\Factions;

use App\Models\ReputationChange;

class UpdateReputationReason
{
    /**
     * The reason only. The delta is not edited: a wrong one is deleted and written
     * again, the ledger's rule, so the sum never moves under a row's feet.
     */
    public function handle(ReputationChange $change, ?string $reason): ReputationChange
    {
        $change->update(['reason' => filled($reason) ? trim((string) $reason) : null]);

        return $change;
    }
}
