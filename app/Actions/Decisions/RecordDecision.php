<?php

namespace App\Actions\Decisions;

use App\Models\Campaign;
use App\Models\Decision;
use App\Models\GameSession;
use App\Models\User;

class RecordDecision
{
    /**
     * A new row, hidden. Hidden is the default the column carries, for the reason a
     * new clock and a new pin are hidden: the GM writes it at the table and the
     * party learns the choice was noted when the GM decides they do.
     */
    public function handle(Campaign $campaign, User $actor, string $choice, ?string $consequence = null, ?GameSession $session = null): Decision
    {
        return Decision::create([
            'campaign_id' => $campaign->id,
            'game_session_id' => $session?->id,
            'choice' => trim($choice),
            'consequence' => filled($consequence) ? trim((string) $consequence) : null,
            'player_visible' => false,
            'created_by' => $actor->id,
        ]);
    }
}
