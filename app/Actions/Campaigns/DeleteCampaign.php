<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;

class DeleteCampaign
{
    public function handle(Campaign $campaign): void
    {
        $campaign->delete();
    }
}
