<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use App\Models\User;
use LogicException;

class LeaveCampaign
{
    public function __construct(private readonly RemoveMember $removeMember) {}

    public function handle(Campaign $campaign, User $user): void
    {
        $member = $campaign->memberFor($user);

        if ($member === null) {
            return;
        }

        if ($member->isOwner()) {
            throw new LogicException('The owner cannot leave. Transfer ownership first.');
        }

        $this->removeMember->handle($member);
    }
}
