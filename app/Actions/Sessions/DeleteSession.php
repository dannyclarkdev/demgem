<?php

namespace App\Actions\Sessions;

use App\Models\GameSession;
use Illuminate\Support\Facades\DB;

class DeleteSession
{
    /**
     * Soft delete. Secrets return to the pool, so they carry into the next session
     * instead of disappearing with the row. Scenes stay attached and go with it.
     */
    public function handle(GameSession $session): void
    {
        DB::transaction(function () use ($session): void {
            $session->secrets()->update(['game_session_id' => null]);

            $session->delete();
        });
    }
}
