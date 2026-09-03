<?php

namespace App\Policies;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\Encounter;
use App\Models\User;
use App\Support\CurrentCampaign;

/**
 * The GM surfaces are GM-only: the index, the encounter page, and the tracker
 * embedded in the Run screen. A player gets 404, never 403.
 *
 * viewTable() is the one exception, and it guards the one player surface: the fight
 * on /table. It answers "may this person watch", never "may they see this row".
 * Which rows they see is combatants.player_visible, and how much of a row they see
 * is Combatant::healthWord().
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
     * Watching a fight from the player side. Surfaces: /table and the Fight component
     * nested in it.
     *
     * Every member, spectators included: a spectator is read-only, and this is the
     * most read-only screen in the app. A co-GM on a second device watches the same
     * page rather than a second Run screen.
     */
    public function viewTable(User $user, Encounter $encounter): bool
    {
        return $this->roleFor($user, $encounter) !== null;
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
