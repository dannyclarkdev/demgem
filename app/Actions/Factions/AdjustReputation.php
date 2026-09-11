<?php

namespace App\Actions\Factions;

use App\Models\Entity;
use App\Models\GameSession;
use App\Models\ReputationChange;
use App\Models\User;
use App\Support\Reputation\Standing;

class AdjustReputation
{
    /**
     * One moment, hidden until the GM says the party noticed. The delta is clamped
     * to the configured range and never zero; a change of nothing is not a change.
     */
    public function handle(Entity $faction, User $actor, int $delta, ?string $reason = null, ?GameSession $session = null): ReputationChange
    {
        $max = Standing::maxDelta();
        $delta = max(-$max, min($max, $delta));

        if ($delta === 0) {
            throw new \InvalidArgumentException('A reputation change cannot be zero.');
        }

        return ReputationChange::create([
            'campaign_id' => $faction->campaign_id,
            'entity_id' => $faction->id,
            'game_session_id' => $session?->id,
            'delta' => $delta,
            'reason' => filled($reason) ? trim((string) $reason) : null,
            'player_visible' => false,
            'created_by' => $actor->id,
        ]);
    }
}
