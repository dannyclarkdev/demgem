<?php

namespace App\Livewire\Dice;

use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\DiceRoll;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The shared dice log. One component, and as many audiences as there are people in
 * the campaign.
 *
 * Everyone who can open the campaign reads it, spectators included: watching is the
 * whole of what a spectator is there for. The filter is DiceRoll::visibleTo(), in the
 * query, so a private roll is never loaded for anyone but the person who made it.
 *
 * It renders in the Run screen drawer under the GM's tray, and on /table under the
 * player's. A roll anywhere reaches both, because every roll broadcasts and every
 * listener re-renders under its own viewer.
 *
 * Nested, so it calls enterCampaign() in its own mount: the hydrate hook runs per
 * component, and a member removed mid-session must stop reading the table's rolls.
 */
class Log extends Component
{
    use InteractsWithCampaign;

    public const LIMIT = 25;

    public const POLL_SECONDS = 60;

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
    }

    /**
     * Somebody rolled. The re-render is the whole listener; the event carries nothing.
     */
    #[On('echo-presence:campaign.{campaign.id},.dice.rolled')]
    public function diceRolled(): void
    {
        // Deliberately empty. The re-render runs under this viewer's own identity, so
        // a private roll stays where it was made.
    }

    /**
     * Clears this person's own rolls and nobody else's, which is what the button says.
     * It does not broadcast: a log that shortened on somebody else's screen without
     * them asking would read as a bug.
     */
    public function clearLog(): void
    {
        DiceRoll::query()->where('user_id', $this->user()->id)->delete();
    }

    public function render(): View
    {
        return view('livewire.dice.log', [
            'rolls' => $this->recentRolls(),
            'yourRolls' => DiceRoll::query()->where('user_id', $this->user()->id)->exists(),
            'pollSeconds' => self::POLL_SECONDS,
        ]);
    }

    /**
     * One eager-loaded query, capped. A table rolls a lot in four hours and nobody
     * scrolls back through it: the history is the export's job, not the drawer's.
     *
     * @return Collection<int, DiceRoll>
     */
    private function recentRolls(): Collection
    {
        return DiceRoll::query()
            ->visibleTo($this->user())
            ->with('user')
            ->latest()
            ->limit(self::LIMIT)
            ->get();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
