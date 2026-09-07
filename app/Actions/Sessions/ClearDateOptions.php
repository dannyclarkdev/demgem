<?php

namespace App\Actions\Sessions;

use App\Models\GameSession;

class ClearDateOptions
{
    /**
     * For the poll left behind when a date was typed into the form instead of picked.
     * Deleting it on that save would be a surprise; this is the GM saying so.
     */
    public function handle(GameSession $session): void
    {
        $session->dateOptions()->delete();
    }
}
