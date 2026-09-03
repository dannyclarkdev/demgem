<?php

namespace App\Actions\Encounters;

use App\Enums\EncounterStatus;
use App\Models\Campaign;
use App\Models\Encounter;
use App\Models\GameSession;
use App\Models\User;

class CreateEncounter
{
    /**
     * The session is set when the GM starts a fight from the Run screen, and null for a
     * one-off built ahead of time.
     */
    public function handle(Campaign $campaign, User $actor, string $name, ?GameSession $session = null): Encounter
    {
        return Encounter::create([
            'campaign_id' => $campaign->id,
            'game_session_id' => $session?->id,
            'name' => trim($name),
            'status' => EncounterStatus::Planning,
            'round' => 0,
            'created_by' => $actor->id,
        ]);
    }
}
