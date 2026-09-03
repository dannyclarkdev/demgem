<?php

namespace App\Livewire\Sessions;

use App\Enums\SessionStatus;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\GameSession;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    use InteractsWithCampaign;

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('viewAny', [GameSession::class, $campaign]);
    }

    public function render(): View
    {
        $role = $this->role();
        $search = mb_strtolower(trim($this->search));

        // A campaign holds tens of sessions, not thousands, and the page groups them
        // by status. Grouping beats paginating here.
        $sessions = GameSession::query()
            ->visibleTo($role)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $q) use ($search): void {
                    $q->whereRaw('lower(coalesce(title, \'\')) like ?', ['%'.$search.'%']);

                    if (is_numeric($search)) {
                        $q->orWhere('number', (int) $search);
                    }
                });
            })
            ->orderBy('number')
            ->get();

        // Soonest first, with undated sessions after every dated one.
        $upcoming = $sessions->filter(fn (GameSession $session) => $this->isUpcoming($session))
            ->sortBy(fn (GameSession $session) => [
                $session->scheduled_at === null,
                $session->scheduled_at->timestamp ?? $session->number,
            ])
            ->values();

        $rest = $sessions->reject(fn (GameSession $session) => $this->isUpcoming($session));

        $needsRecap = $role->isDm()
            ? $rest->filter(fn (GameSession $session) => $session->needsRecap())->sortByDesc('number')->values()
            : new Collection;

        $past = $rest->reject(fn (GameSession $session) => $needsRecap->contains($session))
            ->sortByDesc('number')
            ->values();

        return view('livewire.sessions.index', [
            'role' => $role,
            'timezone' => $this->campaign->timezone,
            'upcoming' => $upcoming,
            'needsRecap' => $needsRecap,
            'past' => $past,
            'total' => $sessions->count(),
        ])->title('Sessions');
    }

    /**
     * Planned sessions wait to be played whatever their date says. A cancelled session
     * stays on the list while its date is ahead, so nobody turns up for a game that is off.
     */
    private function isUpcoming(GameSession $session): bool
    {
        if ($session->status === SessionStatus::Planned) {
            return true;
        }

        return $session->status === SessionStatus::Cancelled
            && $session->scheduled_at !== null
            && $session->scheduled_at->isFuture();
    }
}
