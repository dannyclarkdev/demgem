<?php

namespace App\Actions\Sessions;

use App\Models\GameSession;
use App\Models\SessionDateOption;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PickDate
{
    /**
     * The poll's job is done: the date goes on the session and the options go, votes
     * with them, in one transaction so a failure leaves the poll standing.
     */
    public function handle(GameSession $session, SessionDateOption $option, User $actor, UpdateSession $updateSession): GameSession
    {
        return DB::transaction(function () use ($session, $option, $actor, $updateSession): GameSession {
            $updated = $updateSession->handle($session, $actor, ['scheduled_at' => $option->starts_at]);

            $session->dateOptions()->delete();

            return $updated;
        });
    }
}
