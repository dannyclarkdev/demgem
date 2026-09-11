<?php

namespace App\Actions\Factions;

use App\Models\ReputationChange;

class SetReputationVisibility
{
    public function handle(ReputationChange $change, bool $visible): void
    {
        if ($change->player_visible === $visible) {
            return;
        }

        $change->update(['player_visible' => $visible]);
    }

    public function toggle(ReputationChange $change): void
    {
        $this->handle($change, ! $change->player_visible);
    }
}
