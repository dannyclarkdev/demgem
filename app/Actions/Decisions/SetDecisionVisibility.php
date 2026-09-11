<?php

namespace App\Actions\Decisions;

use App\Models\Decision;

class SetDecisionVisibility
{
    /**
     * Shows or hides one row on the party's log. Revealing a decision does not
     * reveal the session it was made in: that link has a gate of its own.
     */
    public function handle(Decision $decision, bool $visible): void
    {
        if ($decision->player_visible === $visible) {
            return;
        }

        $decision->update(['player_visible' => $visible]);
    }

    public function toggle(Decision $decision): void
    {
        $this->handle($decision, ! $decision->player_visible);
    }
}
