<?php

namespace App\Livewire\Downtime;

use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\DowntimeActivity;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * The page around the log: a total per character, then the whole campaign's rows.
 * Every member; what each reads is the log's own query, and the totals here go
 * through the same gate, so a GM-only character's month is not in a player's sum.
 */
class Index extends Component
{
    use InteractsWithCampaign;

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('viewAny', [DowntimeActivity::class, $campaign]);
    }

    public function render(): View
    {
        return view('livewire.downtime.index', [
            'role' => $this->role(),
            'totals' => $this->totals(),
        ])->title('Downtime');
    }

    /**
     * Days per character, summed on every read over the rows this viewer may see,
     * in name order. Two queries: the sums, then the names for the ids that came back.
     *
     * @return Collection<int, array{character: Entity, days: int}>
     */
    private function totals(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        $sums = DowntimeActivity::query()
            ->visibleTo($user, $this->role())
            ->selectRaw('entity_id, sum(days) as total_days')
            ->groupBy('entity_id')
            ->pluck('total_days', 'entity_id');

        if ($sums->isEmpty()) {
            return collect();
        }

        return Entity::query()
            ->whereKey($sums->keys()->all())
            ->orderBy('name')
            ->get()
            ->map(fn (Entity $character) => ['character' => $character, 'days' => (int) $sums[$character->id]])
            ->values();
    }
}
