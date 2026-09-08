<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CampaignMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // Through the membership rows rather than the pivot, so the role is the cast
            // enum. The controller loads only rows whose campaign still exists.
            'campaigns' => $this->campaignMemberships
                ->sortBy(fn (CampaignMember $member) => $member->campaign->name)
                ->map(fn (CampaignMember $member) => [
                    'id' => $member->campaign->id,
                    'name' => $member->campaign->name,
                    'role' => $member->role->value,
                ])
                ->values()
                ->all(),
        ];
    }
}
