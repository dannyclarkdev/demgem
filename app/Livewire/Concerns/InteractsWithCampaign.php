<?php

namespace App\Livewire\Concerns;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Support\CurrentCampaign;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * For Livewire pages that live inside a campaign. Call enterCampaign() from mount().
 * The hydrate hook re-enters on every Livewire update, because route middleware
 * does not run for those requests.
 */
trait InteractsWithCampaign
{
    use AuthorizesRequests;

    public Campaign $campaign;

    public function hydrateInteractsWithCampaign(): void
    {
        $this->enterCampaign($this->campaign);
    }

    protected function enterCampaign(Campaign $campaign): void
    {
        $this->campaign = $campaign;

        $member = $campaign->memberFor(auth()->user());

        abort_if($member === null, 404);

        app(CurrentCampaign::class)->set($campaign, $member->role);
    }

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
