<?php

namespace App\Livewire\Table;

use App\Enums\EncounterStatus;
use App\Enums\EntityType;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
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
 * It is the player's half of the Run screen: the fight while there is one, and the
 * party and the latest recap while there is not, so the page is never empty between
 * fights. The shared dice log joins it in P3.
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

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $role = $this->role();

        return view('livewire.table.show', [
            'role' => $role,
            'fight' => $this->activeEncounter(),
            'pollSeconds' => self::POLL_SECONDS,
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
