<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\CampaignMember;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Who this key belongs to, and which campaigns they are in. The first call a script
 * makes, because every other route needs a campaign id from this list.
 */
class MeController extends Controller
{
    public function __invoke(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        // whereHas drops the membership of a soft-deleted campaign, which a plain
        // eager load would hand the resource as a row with nothing behind it.
        $user->setRelation('campaignMemberships', CampaignMember::query()
            ->where('user_id', $user->id)
            ->whereHas('campaign')
            ->with('campaign')
            ->get());

        return new UserResource($user);
    }
}
