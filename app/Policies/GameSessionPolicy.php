<?php

namespace App\Policies;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\User;
use App\Support\CurrentCampaign;

/**
 * Surfaces that must all use GameSession::visibleTo(): sessions index, session page,
 * dashboard cards, sidebar count, and the "Appears in sessions" panel on an entity.
 * This policy guards direct access.
 *
 * Scenes, secrets, and prepped entities have no policy of their own. They authorize
 * through update() on the session that owns them.
 */
class GameSessionPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user) !== null;
    }

    public function view(User $user, GameSession $session): bool
    {
        $role = $this->roleFor($user, $session);

        return $role !== null && $session->isVisibleTo($role);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    public function update(User $user, GameSession $session): bool
    {
        return $this->roleFor($user, $session)?->isDm() ?? false;
    }

    public function delete(User $user, GameSession $session): bool
    {
        return $this->roleFor($user, $session)?->isDm() ?? false;
    }

    /**
     * Strong start, scenes, secrets, prepped entities, live notes, GM notes, and an
     * unpublished recap. Everything except the published recap and the schedule.
     */
    public function viewDmFields(User $user, GameSession $session): bool
    {
        return $this->roleFor($user, $session)?->isDm() ?? false;
    }

    public function publishRecap(User $user, GameSession $session): bool
    {
        return $this->roleFor($user, $session)?->isDm() ?? false;
    }

    /**
     * The request context answers first, so a page load costs no extra query. A nested
     * Livewire component or a job without that context falls back to the database, which
     * is what keeps a removed member from writing on their next request.
     */
    private function roleFor(User $user, GameSession $session): ?CampaignRole
    {
        $current = app(CurrentCampaign::class);

        if ($current->isSet() && $current->id() === $session->campaign_id && $user->is(auth()->user())) {
            return $current->role();
        }

        return $session->campaign->roleFor($user);
    }
}
