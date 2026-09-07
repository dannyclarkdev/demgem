<?php

namespace App\Actions\Sessions;

use App\Models\GameSession;
use App\Models\SessionRsvp;
use App\Models\User;

class RecordAttendance
{
    /**
     * Marks one member present or absent. The mark is a fact of its own: a "no" who
     * turned up is recorded as there, and a "yes" who did not is recorded as not.
     */
    public function handle(GameSession $session, User $member, bool $attended): void
    {
        $row = SessionRsvp::query()->firstOrNew([
            'game_session_id' => $session->id,
            'user_id' => $member->id,
        ]);

        $row->campaign_id = $session->campaign_id;
        $row->attended = $attended;
        $row->save();
    }
}
