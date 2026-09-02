<?php

namespace App\Livewire\Campaigns;

use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithCampaign;

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
    }

    public function render(): View
    {
        $activeInvites = $this->isDm()
            ? $this->campaign->invites()->whereNull('revoked_at')->get()->filter->isValid()->count()
            : null;

        return view('livewire.campaigns.show', [
            'role' => $this->role(),
            'membersCount' => $this->campaign->members()->count(),
            'activeInvites' => $activeInvites,
        ])->title($this->campaign->name);
    }
}
