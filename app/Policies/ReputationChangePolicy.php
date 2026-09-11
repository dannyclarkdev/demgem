<?php

namespace App\Policies;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\ReputationChange;
use App\Models\User;
use App\Support\CurrentCampaign;

/**
 * A GM's to write, reveal, and take back. Every member reads the faction's page,
 * and what a player reads of the log is a query filter, ReputationChange::visibleTo().
 */
class ReputationChangePolicy
{
    public function create(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    public function update(User $user, ReputationChange $change): bool
    {
        return $this->roleFor($user, $change)?->isDm() ?? false;
    }

    public function delete(User $user, ReputationChange $change): bool
    {
        return $this->roleFor($user, $change)?->isDm() ?? false;
    }

    private function roleFor(User $user, ReputationChange $change): ?CampaignRole
    {
        $current = app(CurrentCampaign::class);

        if ($current->isSet() && $current->id() === $change->campaign_id && $user->is(auth()->user())) {
            return $current->role();
        }

        return $change->campaign->roleFor($user);
    }
}
