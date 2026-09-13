<?php

namespace App\Actions\Downtime;

use App\Models\DowntimeActivity;

class DeleteDowntime
{
    public function handle(DowntimeActivity $activity): void
    {
        $activity->delete();
    }
}
