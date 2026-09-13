<?php

namespace App\Actions\Downtime;

use App\Models\Campaign;
use App\Models\DowntimeActivity;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\User;
use App\Support\Reckoning\GameDate;

class RecordDowntime
{
    /**
     * One row. The parts arrive validated: the component checked the day count
     * against the bounds and the date against the calendar before it got here.
     *
     * @param  array{activity: string, days: int, notes?: string|null, session?: GameSession|null, starts_on?: GameDate|null}  $parts
     */
    public function handle(Campaign $campaign, User $actor, Entity $character, array $parts): DowntimeActivity
    {
        return DowntimeActivity::create([
            'campaign_id' => $campaign->id,
            'entity_id' => $character->id,
            'game_session_id' => ($parts['session'] ?? null)?->id,
            'activity' => trim($parts['activity']),
            'days' => $parts['days'],
            'notes' => filled($parts['notes'] ?? null) ? trim((string) $parts['notes']) : null,
            'starts_on' => $parts['starts_on'] ?? null,
            'created_by' => $actor->id,
        ]);
    }
}
