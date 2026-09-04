<?php

namespace App\Livewire\Table;

use App\Enums\EncounterStatus;
use App\Enums\EntityType;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Clock;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The one page a player keeps open during a game.
 *
 * It is the player's half of the Run screen: the fight while there is one, the dice
 * the whole table shares, and the party and the latest recap, so the page is never
 * empty between fights.
 *
 * This component finds the fight; Table\Fight renders it. The split is what lets a
 * fight start, end, or be replaced without a refresh: this one re-renders on the same
 * broadcast, and the nested component is keyed by encounter id, so a different fight
 * mounts a fresh one.
 *
 * Open to every member. A spectator watches, a co-GM watches from a second device,
 * and the GM's own version of this screen is the Run screen.
 */
class Show extends Component
{
    use InteractsWithCampaign;

    public const POLL_SECONDS = 60;

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
    }

    /**
     * A fight started, ended, or was replaced. The re-render picks the new one up and
     * re-keys the nested component; nothing is read from the payload.
     */
    #[On('echo-presence:campaign.{campaign.id},.encounter.changed')]
    public function encounterChanged(): void
    {
        // Deliberately empty. The re-render is the point, and it runs under this
        // viewer's own role, so every visibility rule applies as it always does.
    }

    /**
     * A clock moved, or the GM revealed the first one. The re-render decides whether
     * the card belongs on the page at all; the panel inside it re-renders itself.
     */
    #[On('echo-presence:campaign.{campaign.id},.clock.changed')]
    public function clockChanged(): void
    {
        // Deliberately empty. The re-render is the point.
    }

    /**
     * A handout changed hands. The re-render decides whether the card belongs on the
     * page; the panel inside it re-renders itself.
     */
    #[On('echo-presence:campaign.{campaign.id},.handout.revealed')]
    public function handoutRevealed(): void
    {
        // Deliberately empty. The re-render is the point.
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $role = $this->role();

        return view('livewire.table.show', [
            'role' => $role,
            'fight' => $this->activeEncounter(),
            'pollSeconds' => self::POLL_SECONDS,
            // One exists() rather than a card that says "nothing counting" for the
            // life of a campaign that never uses clocks. The fight has an empty state
            // because a table expects a fight; clocks are something a GM opts into.
            'hasClocks' => Clock::query()->visibleTo($role)->exists(),
            // Same reasoning as the clocks card: a campaign that hands nothing over
            // should not carry a card that says so.
            'hasHandouts' => Entity::query()->ofType(EntityType::Handout)->visibleTo($user, $role)->exists(),
            // A spectator reads the log and gets no tray. Watching is what they are
            // here for, and rolling is not.
            'mayRoll' => $user->can('rollDice', $this->campaign),
            'party' => Entity::query()
                ->ofType(EntityType::Character)
                ->visibleTo($user, $role)
                ->where('is_pc', true)
                ->with('player')
                ->orderBy('name')
                ->get(),
            'latestRecap' => GameSession::query()
                ->visibleTo($role)
                ->whereNotNull('recap_published_at')
                ->orderByDesc('recap_published_at')
                ->first(),
        ])->title('The table');
    }

    /**
     * The fight on the table right now. Status decides it, not the clock: a GM starts
     * one with "Start" and ends it with "End", and both write the status.
     *
     * The most recently touched one wins when two are somehow open, because the one a
     * GM last clicked is the one the party is looking at.
     */
    private function activeEncounter(): ?Encounter
    {
        return Encounter::query()
            ->where('status', EncounterStatus::Active)
            ->orderByDesc('updated_at')
            ->first();
    }
}
