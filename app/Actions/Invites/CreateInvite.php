<?php

namespace App\Actions\Invites;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\CampaignInvite;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateInvite
{
    public function handle(
        Campaign $campaign,
        User $creator,
        CampaignRole $role,
        ?int $maxUses = null,
        ?CarbonInterface $expiresAt = null,
    ): CampaignInvite {
        if (! in_array($role, CampaignRole::invitable(), true)) {
            throw new InvalidArgumentException("An invite cannot grant the {$role->value} role.");
        }

        return $campaign->invites()->create([
            'token' => Str::random(40),
            'role' => $role,
            'max_uses' => $maxUses,
            'expires_at' => $expiresAt,
            'created_by' => $creator->id,
        ]);
    }
}
