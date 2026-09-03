<?php

namespace App\Actions\Sessions;

use App\Models\GameSession;
use App\Models\Secret;

class RevealSecret
{
    /**
     * Records where a secret came out, which is not always where it was prepared.
     */
    public function handle(Secret $secret, GameSession $session): void
    {
        $secret->update([
            'revealed_at' => now(),
            'revealed_in_session_id' => $session->id,
        ]);
    }

    /**
     * The party did not actually learn it. Put it back in play.
     */
    public function undo(Secret $secret): void
    {
        $secret->update([
            'revealed_at' => null,
            'revealed_in_session_id' => null,
        ]);
    }
}
