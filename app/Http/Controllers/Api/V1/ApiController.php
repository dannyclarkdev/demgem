<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CampaignRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CurrentCampaign;

/**
 * The two facts every campaign route needs: who holds the key, and what they are in
 * this campaign. EnsureCampaignMember set the second before the controller ran.
 */
abstract class ApiController extends Controller
{
    protected function viewer(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    protected function role(): CampaignRole
    {
        $role = app(CurrentCampaign::class)->role();

        abort_if($role === null, 404);

        return $role;
    }
}
