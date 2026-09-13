<?php

namespace App\Actions\Downtime;

use App\Models\DowntimeActivity;
use App\Models\GameSession;
use App\Support\Reckoning\GameDate;

class UpdateDowntime
{
    /**
     * The same parts as RecordDowntime, on a row. The character never changes: a row
     * written on the wrong character is deleted and written again.
     *
     * @param  array{activity: string, days: int, notes?: string|null, session?: GameSession|null, starts_on?: GameDate|null}  $parts
     */
    public function handle(DowntimeActivity $activity, array $parts): DowntimeActivity
    {
        $activity->fill([
            'game_session_id' => ($parts['session'] ?? null)?->id,
            'activity' => trim($parts['activity']),
            'days' => $parts['days'],
            'notes' => filled($parts['notes'] ?? null) ? trim((string) $parts['notes']) : null,
            'starts_on' => $parts['starts_on'] ?? null,
        ])->save();

        return $activity;
    }
}
