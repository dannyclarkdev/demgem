<?php

namespace App\Actions\Sessions;

use App\Models\GameSession;
use App\Models\SessionDateOption;
use Carbon\CarbonInterface;

class AddDateOption
{
    /**
     * Appends a candidate time. The caller has already converted it to UTC from the
     * campaign's zone, the way the session form does.
     */
    public function handle(GameSession $session, CarbonInterface $startsAt): SessionDateOption
    {
        $position = (int) $session->dateOptions()->max('position');

        return $session->dateOptions()->create([
            'campaign_id' => $session->campaign_id,
            'starts_at' => $startsAt,
            'position' => $session->dateOptions()->exists() ? $position + 1 : 0,
        ]);
    }
}
