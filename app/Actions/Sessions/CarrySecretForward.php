<?php

namespace App\Actions\Sessions;

use App\Models\GameSession;
use App\Models\Secret;

class CarrySecretForward
{
    /**
     * Pins a waiting secret to this session. It goes to the end of the list, so it never
     * jumps above what the GM wrote today.
     */
    public function handle(Secret $secret, GameSession $session): void
    {
        $highest = $session->secrets()->max('position');

        $secret->update([
            'game_session_id' => $session->id,
            'position' => $highest === null ? 0 : ((int) $highest) + 1,
        ]);
    }
}
