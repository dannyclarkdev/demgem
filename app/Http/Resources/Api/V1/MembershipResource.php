<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CampaignMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A campaign as one member sees it. The membership row is the resource because the
 * role is the one fact about a campaign that differs per key holder.
 *
 * @mixin CampaignMember
 */
class MembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $campaign = $this->campaign;
        $calendar = $campaign->calendar;

        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'ruleset' => $campaign->ruleset->value,
            'timezone' => $campaign->timezone,
            'role' => $this->role->value,
            'calendar' => $calendar === null ? null : [
                'name' => $calendar->name,
                'today' => $calendar->today()->toArray(),
                'today_formatted' => $calendar->todayFormatted(),
            ],
            'url' => route('campaigns.show', $campaign),
        ];
    }
}
