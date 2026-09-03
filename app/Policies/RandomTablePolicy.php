<?php

namespace App\Policies;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\RandomTable;
use App\Models\User;
use App\Support\CurrentCampaign;

/**
 * Random tables are GM-only on every surface: the index, the table page, and the
 * roller in the Run screen drawer. A player gets 404, never 403.
 *
 * Entries have no policy of their own. They authorize through update() on the table.
 */
class RandomTablePolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    public function view(User $user, RandomTable $table): bool
    {
        return $this->roleFor($user, $table)?->isDm() ?? false;
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    public function update(User $user, RandomTable $table): bool
    {
        return $this->roleFor($user, $table)?->isDm() ?? false;
    }

    public function delete(User $user, RandomTable $table): bool
    {
        return $this->roleFor($user, $table)?->isDm() ?? false;
    }

    /**
     * The request context answers first, so a page load costs no extra query. A nested
     * Livewire component or a job without that context falls back to the database,
     * which is what keeps a removed member from writing on their next request.
     */
    private function roleFor(User $user, RandomTable $table): ?CampaignRole
    {
        $current = app(CurrentCampaign::class);

        if ($current->isSet() && $current->id() === $table->campaign_id && $user->is(auth()->user())) {
            return $current->role();
        }

        return $table->campaign->roleFor($user);
    }
}
