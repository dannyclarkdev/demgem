<?php

namespace App\Http\Resources\Api\V1\Concerns;

use App\Enums\CampaignRole;
use App\Support\CurrentCampaign;

/**
 * A resource decides its DM-only keys from the role EnsureCampaignMember recorded,
 * the way a Blade reads $role. A key the viewer may not see is left out, not nulled,
 * so a diff of two roles' documents shows the gate.
 */
trait ReadsTheViewerRole
{
    protected function role(): CampaignRole
    {
        $role = app(CurrentCampaign::class)->role();

        abort_if($role === null, 404);

        return $role;
    }

    protected function isDm(): bool
    {
        return $this->role()->isDm();
    }
}
