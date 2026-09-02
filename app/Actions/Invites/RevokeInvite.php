<?php

namespace App\Actions\Invites;

use App\Models\CampaignInvite;

class RevokeInvite
{
    public function handle(CampaignInvite $invite): void
    {
        if ($invite->isRevoked()) {
            return;
        }

        $invite->update(['revoked_at' => now()]);
    }
}
