<?php

namespace App\Policies;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\User;

class CampaignPolicy
{
    public function view(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user) !== null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user) === CampaignRole::Owner;
    }

    public function manageMembers(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    /**
     * The GM's table tools: the encounter tracker and the random tables. The dice tray
     * left this ability in slice 5, when the log became shared; see rollDice().
     */
    public function useGmTools(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    /**
     * Rolling dice. Surfaces: the tray in the Run screen drawer and the tray on /table.
     *
     * Owner, co-GM, and player. Not a spectator, who is read-only by definition and
     * whose roll nobody at the table asked for. A spectator still reads the log: it is
     * what is happening, and watching is what they are there for.
     */
    public function rollDice(User $user, Campaign $campaign): bool
    {
        $role = $campaign->roleFor($user);

        return $role !== null && $role !== CampaignRole::Spectator;
    }

    /**
     * The JSON export. Surfaces: the download route and the card in campaign settings.
     *
     * GM roles, not the owner alone: a co-GM already sees every field the file holds,
     * so an owner-only rule would protect nothing and lose the campaign when the owner
     * disappears, which is the case the export exists for.
     */
    public function export(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    public function changeRoles(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user) === CampaignRole::Owner;
    }

    public function transferOwnership(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user) === CampaignRole::Owner;
    }

    public function createInvite(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    /**
     * The sole owner cannot leave. They transfer ownership first.
     */
    public function leave(User $user, Campaign $campaign): bool
    {
        $role = $campaign->roleFor($user);

        return $role !== null && $role !== CampaignRole::Owner;
    }

    /**
     * Owner removes anyone but themselves. Co-GM removes players and spectators only.
     */
    public function removeMember(User $user, Campaign $campaign, CampaignMember $member): bool
    {
        $actor = $campaign->roleFor($user);

        if ($actor === null || ! $actor->isDm()) {
            return false;
        }

        if ($member->campaign_id !== $campaign->id || $member->user_id === $user->id) {
            return false;
        }

        if ($actor === CampaignRole::Owner) {
            return true;
        }

        return ! $member->role->isDm();
    }
}
