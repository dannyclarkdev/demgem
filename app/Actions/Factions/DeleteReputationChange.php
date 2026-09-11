<?php

namespace App\Actions\Factions;

use App\Models\ReputationChange;

class DeleteReputationChange
{
    public function handle(ReputationChange $change): void
    {
        $change->delete();
    }
}
