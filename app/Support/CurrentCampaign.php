<?php

namespace App\Support;

use App\Enums\CampaignRole;
use App\Models\Campaign;

/**
 * Request-scoped holder for the campaign the viewer is inside and their role in it.
 * Set by EnsureCampaignMember for HTTP routes and by InteractsWithCampaign for Livewire updates.
 */
final class CurrentCampaign
{
    private ?Campaign $campaign = null;

    private ?CampaignRole $role = null;

    public function set(Campaign $campaign, CampaignRole $role): void
    {
        $this->campaign = $campaign;
        $this->role = $role;
    }

    public function clear(): void
    {
        $this->campaign = null;
        $this->role = null;
    }

    public function get(): ?Campaign
    {
        return $this->campaign;
    }

    public function id(): ?string
    {
        return $this->campaign?->id;
    }

    public function role(): ?CampaignRole
    {
        return $this->role;
    }

    public function isSet(): bool
    {
        return $this->campaign !== null;
    }

    public function isDm(): bool
    {
        return $this->role?->isDm() ?? false;
    }
}
