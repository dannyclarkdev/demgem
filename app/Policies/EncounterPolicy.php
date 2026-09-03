<?php

namespace App\Policies;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\Encounter;
use App\Models\User;
use App\Support\CurrentCampaign;

/**
 * Encounters are GM-only on every surface: the index, the encounter page, and the
 * tracker embedded in the Run screen. A player gets 404, never 403.
 *
 * Combatants have no policy of their own. They authorize through update() on the
 * encounter that owns them.
 */
class EncounterPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    public function view(User $user, Encounter $encounter): bool
    {
        return $this->roleFor($user, $encounter)?->isDm() ?? false;
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    public function update(User $user, Encounter $encounter): bool
    {
        return $this->roleFor($user, $encounter)?->isDm() ?? false;
    }

    public function delete(User $user, Encounter $encounter): bool
    {
        return $this->roleFor($user, $encounter)?->isDm() ?? false;
    }

    /**
     * The request context answers first, so a page load costs no extra query. A nested
     * Livewire component or a job without that context falls back to the database,
     * which is what keeps a removed member from writing on their next request. A
     * polling tracker makes that sharper: without it a demoted co-GM would keep
     * pulling the encounter every fifteen seconds.
     */
    private function roleFor(User $user, Encounter $encounter): ?CampaignRole
    {
        $current = app(CurrentCampaign::class);

        if ($current->isSet() && $current->id() === $encounter->campaign_id && $user->is(auth()->user())) {
            return $current->role();
        }

        return $encounter->campaign->roleFor($user);
    }
}
