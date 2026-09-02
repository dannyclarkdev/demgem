<?php

namespace App\Actions\Campaigns;

use App\Models\CampaignMember;
use App\Models\Entity;
use Illuminate\Support\Facades\DB;
use LogicException;

class RemoveMember
{
    /**
     * Removes the membership. Their PC stays in the campaign with no player attached.
     */
    public function handle(CampaignMember $member): void
    {
        if ($member->isOwner()) {
            throw new LogicException('Transfer ownership before you remove the owner.');
        }

        DB::transaction(function () use ($member): void {
            Entity::withoutGlobalScopes()
                ->where('campaign_id', $member->campaign_id)
                ->where('player_user_id', $member->user_id)
                ->update(['player_user_id' => null]);

            $member->delete();
        });

        $member->campaign->forgetMemberCache();
    }
}
