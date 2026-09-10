<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\StatBlock;
use App\Models\User;

/**
 * Who may write a creature.
 *
 * Only a GM, and only one their own campaign wrote. A shipped row has no writer but
 * demgem:import-srd: no role reaches it here, and CreateStatBlock's siblings refuse one
 * even when called directly, because a policy guards a request and reference data whose
 * checksum is pinned in configuration needs guarding against every caller.
 *
 * Reading is CampaignPolicy::viewCompendium(), which is one gate over both halves of
 * the book, and StatBlock::scopeForCampaign() is what decides which rows are in it.
 */
class StatBlockPolicy
{
    public function create(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    public function update(User $user, StatBlock $statBlock): bool
    {
        if ($statBlock->isShipped()) {
            return false;
        }

        $campaign = $statBlock->campaign;

        return $campaign !== null && $this->create($user, $campaign);
    }

    public function delete(User $user, StatBlock $statBlock): bool
    {
        return $this->update($user, $statBlock);
    }
}
