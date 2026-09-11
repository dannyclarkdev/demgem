<?php

namespace App\Policies;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\CurrentCampaign;

/**
 * The purse is the party's. Every member reads it and writes to it; a row is deleted
 * by whoever wrote it or by a GM, and never edited, because a ledger is corrected by
 * a second row and a deleted mistake.
 */
class LedgerEntryPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user) !== null;
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user) !== null;
    }

    public function delete(User $user, LedgerEntry $entry): bool
    {
        $role = $this->roleFor($user, $entry);

        if ($role === null) {
            return false;
        }

        return $role->isDm() || $entry->created_by === $user->id;
    }

    private function roleFor(User $user, LedgerEntry $entry): ?CampaignRole
    {
        $current = app(CurrentCampaign::class);

        if ($current->isSet() && $current->id() === $entry->campaign_id && $user->is(auth()->user())) {
            return $current->role();
        }

        return $entry->campaign->roleFor($user);
    }
}
