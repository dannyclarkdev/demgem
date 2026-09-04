<?php

namespace App\Policies;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\Clock;
use App\Models\User;
use App\Support\CurrentCampaign;

/**
 * A clock is a GM tool. Only a GM makes one, turns one, or takes one away.
 *
 * A player reads the revealed ones on the table screen and on an entity page, and
 * that read is a query filter rather than an ability: Clock::visibleTo() decides it,
 * so there is no view() here for a player to be granted by accident.
 */
class ClockPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    public function update(User $user, Clock $clock): bool
    {
        return $this->roleFor($user, $clock)?->isDm() ?? false;
    }

    public function delete(User $user, Clock $clock): bool
    {
        return $this->roleFor($user, $clock)?->isDm() ?? false;
    }

    /**
     * The request context answers first, so a page load costs no extra query. A nested
     * Livewire component or a job without that context falls back to the database,
     * which is what keeps a removed member from writing on their next request.
     */
    private function roleFor(User $user, Clock $clock): ?CampaignRole
    {
        $current = app(CurrentCampaign::class);

        if ($current->isSet() && $current->id() === $clock->campaign_id && $user->is(auth()->user())) {
            return $current->role();
        }

        return $clock->campaign->roleFor($user);
    }
}
