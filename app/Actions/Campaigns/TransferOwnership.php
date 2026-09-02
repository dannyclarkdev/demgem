<?php

namespace App\Actions\Campaigns;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\CampaignMember;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferOwnership
{
    public function handle(Campaign $campaign, CampaignMember $newOwner): void
    {
        if ($newOwner->campaign_id !== $campaign->id) {
            throw new InvalidArgumentException('The new owner must be a member of the campaign.');
        }

        DB::transaction(function () use ($campaign, $newOwner): void {
            $currentOwner = $campaign->owner()->lockForUpdate()->firstOrFail();

            if ($currentOwner->is($newOwner)) {
                return;
            }

            $currentOwner->update(['role' => CampaignRole::CoGm]);
            $newOwner->update(['role' => CampaignRole::Owner]);
        });

        $campaign->forgetMemberCache();
    }
}
