<?php

namespace App\Policies;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\DowntimeActivity;
use App\Models\Entity;
use App\Models\User;
use App\Support\CurrentCampaign;

/**
 * A player writes their own PC's downtime; a GM writes anyone's. That is the rule
 * EntityPolicy::update() already spells for the character itself, read from the
 * same two facts: the role and entities.player_user_id. Editing and deleting follow
 * the character, not the author: a GM who wrote a row on Wren's page wrote it for
 * Wren's player to correct.
 *
 * Every member reads the log, and what a player reads is a query filter rather than
 * an ability: DowntimeActivity::visibleTo() decides it, so there is no view() here.
 */
class DowntimeActivityPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user) !== null;
    }

    public function create(User $user, Entity $character): bool
    {
        return $this->mayWriteOn($user, $character);
    }

    public function update(User $user, DowntimeActivity $activity): bool
    {
        return $this->mayWriteOn($user, $activity->character);
    }

    public function delete(User $user, DowntimeActivity $activity): bool
    {
        return $this->mayWriteOn($user, $activity->character);
    }

    private function mayWriteOn(User $user, Entity $character): bool
    {
        $role = $this->roleFor($user, $character);

        if ($role === null) {
            return false;
        }

        return $role->isDm() || $character->player_user_id === $user->id;
    }

    /**
     * The request context answers first, so a page load costs no extra query. A nested
     * component without that context falls back to the database, which is what keeps
     * a removed member from writing on their next request.
     */
    private function roleFor(User $user, Entity $character): ?CampaignRole
    {
        $current = app(CurrentCampaign::class);

        if ($current->isSet() && $current->id() === $character->campaign_id && $user->is(auth()->user())) {
            return $current->role();
        }

        return $character->campaign->roleFor($user);
    }
}
