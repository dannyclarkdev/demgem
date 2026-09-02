<?php

namespace App\Policies;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use App\Support\CurrentCampaign;

/**
 * Visibility surfaces that must all use Entity::visibleTo(): index, search, autocomplete,
 * backlinks, tag counts, children, breadcrumbs, sidebar counts. This policy guards direct access.
 */
class EntityPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user) !== null;
    }

    public function view(User $user, Entity $entity): bool
    {
        $role = $this->roleFor($user, $entity);

        return $role !== null && $entity->isVisibleTo($user, $role);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    /**
     * DM roles edit anything. A player edits their own PC, limited to non-DM fields.
     */
    public function update(User $user, Entity $entity): bool
    {
        $role = $this->roleFor($user, $entity);

        if ($role === null) {
            return false;
        }

        return $role->isDm() || $entity->player_user_id === $user->id;
    }

    public function delete(User $user, Entity $entity): bool
    {
        return $this->roleFor($user, $entity)?->isDm() ?? false;
    }

    public function viewDmNotes(User $user, Entity $entity): bool
    {
        return $this->roleFor($user, $entity)?->isDm() ?? false;
    }

    /**
     * Visibility, DM notes, parent, PC flag, player assignment, viewers.
     */
    public function updateDmFields(User $user, Entity $entity): bool
    {
        return $this->roleFor($user, $entity)?->isDm() ?? false;
    }

    private function roleFor(User $user, Entity $entity): ?CampaignRole
    {
        $current = app(CurrentCampaign::class);

        if ($current->isSet() && $current->id() === $entity->campaign_id && $user->is(auth()->user())) {
            return $current->role();
        }

        return $entity->campaign->roleFor($user);
    }
}
