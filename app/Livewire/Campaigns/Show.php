<?php

namespace App\Livewire\Campaigns;

use App\Enums\CampaignRole;
use App\Enums\SessionStatus;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\GameSession;
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
        $role = $this->role();

        $activeInvites = $this->isDm()
            ? $this->campaign->invites()->whereNull('revoked_at')->get()->filter->isValid()->count()
            : null;

        return view('livewire.campaigns.show', [
            'role' => $role,
            'membersCount' => $this->campaign->members()->count(),
            'activeInvites' => $activeInvites,
            'timezone' => $this->campaign->timezone,
            'nextSession' => $this->nextSession($role),
            'latestRecap' => GameSession::query()
                ->visibleTo($role)
                ->whereNotNull('recap_published_at')
                ->orderByDesc('recap_published_at')
                ->first(),
        ])->title($this->campaign->name);
    }

    /**
     * The soonest planned session with a date. A GM often preps before the group picks
     * one, so fall back to the first undated planned session rather than showing nothing.
     */
    private function nextSession(CampaignRole $role): ?GameSession
    {
        $dated = GameSession::query()
            ->visibleTo($role)
            ->where('status', SessionStatus::Planned)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->first();

        return $dated ?? GameSession::query()
            ->visibleTo($role)
            ->where('status', SessionStatus::Planned)
            ->whereNull('scheduled_at')
            ->orderBy('number')
            ->first();
    }
}
