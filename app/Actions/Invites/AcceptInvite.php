<?php

namespace App\Actions\Invites;

use App\Exceptions\InvalidInviteException;
use App\Models\CampaignInvite;
use App\Models\CampaignMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptInvite
{
    /**
     * Joins the user to the invite's campaign. Locks the invite row so concurrent
     * accepts cannot exceed max_uses. Existing members keep their current role.
     */
    public function handle(CampaignInvite $invite, User $user): CampaignMember
    {
        return DB::transaction(function () use ($invite, $user): CampaignMember {
            $locked = CampaignInvite::withoutGlobalScopes()
                ->whereKey($invite->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isValid()) {
                throw new InvalidInviteException;
            }

            $campaign = $locked->campaign;
            $existing = $campaign->memberFor($user);

            if ($existing !== null) {
                return $existing;
            }

            $member = $campaign->members()->create([
                'user_id' => $user->id,
                'role' => $locked->role,
            ]);

            $locked->increment('uses');
            $campaign->forgetMemberCache();

            return $member;
        });
    }
}
