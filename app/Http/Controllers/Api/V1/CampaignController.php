<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\MembershipResource;
use App\Models\Campaign;
use App\Models\CampaignMember;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CampaignController extends ApiController
{
    /**
     * The campaigns this key holder belongs to, as their membership rows: the role is
     * the one fact about a campaign that differs per person.
     */
    public function index(): AnonymousResourceCollection
    {
        $memberships = CampaignMember::query()
            ->where('user_id', $this->viewer()->id)
            ->whereHas('campaign')
            ->with('campaign.calendar')
            ->get()
            ->sortBy(fn (CampaignMember $member) => $member->campaign->name)
            ->values();

        return MembershipResource::collection($memberships);
    }

    public function show(Campaign $campaign): MembershipResource
    {
        $member = $campaign->memberFor($this->viewer());

        abort_if($member === null, 404);

        $member->setRelation('campaign', $campaign->load('calendar'));

        return new MembershipResource($member);
    }
}
