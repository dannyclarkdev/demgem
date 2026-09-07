<?php

namespace App\Actions\Sessions;

use App\Enums\Rsvp;
use App\Models\GameSession;
use App\Models\SessionRsvp;
use App\Models\User;

class RespondToSession
{
    /**
     * Writes one member's answer. A cleared answer on a row with no attendance
     * deletes the row, so "never answered" and "cleared their answer" look the same.
     */
    public function handle(GameSession $session, User $member, ?Rsvp $rsvp): void
    {
        $row = SessionRsvp::query()->firstOrNew([
            'game_session_id' => $session->id,
            'user_id' => $member->id,
        ]);

        $row->campaign_id = $session->campaign_id;
        $row->rsvp = $rsvp;

        if ($row->isEmpty()) {
            if ($row->exists) {
                $row->delete();
            }

            return;
        }

        $row->save();
    }
}
