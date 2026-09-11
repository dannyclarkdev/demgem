<?php

namespace App\Policies;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\Decision;
use App\Models\User;
use App\Support\CurrentCampaign;

/**
 * A decision is the GM's to write, reveal, and take back. Every member reads the
 * log, and what a player reads is a query filter rather than an ability:
 * Decision::visibleTo() decides it, so there is no view() here to grant by accident.
 */
class DecisionPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user) !== null;
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    public function update(User $user, Decision $decision): bool
    {
        return $this->roleFor($user, $decision)?->isDm() ?? false;
    }

    public function delete(User $user, Decision $decision): bool
    {
        return $this->roleFor($user, $decision)?->isDm() ?? false;
    }

    /**
     * The request context answers first, so a page load costs no extra query. A nested
     * component without that context falls back to the database, which is what keeps
     * a removed member from writing on their next request.
     */
    private function roleFor(User $user, Decision $decision): ?CampaignRole
    {
        $current = app(CurrentCampaign::class);

        if ($current->isSet() && $current->id() === $decision->campaign_id && $user->is(auth()->user())) {
            return $current->role();
        }

        return $decision->campaign->roleFor($user);
    }
}
