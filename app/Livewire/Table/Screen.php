<?php

namespace App\Livewire\Table;

use App\Enums\CampaignRole;
use App\Enums\EncounterStatus;
use App\Enums\EntityType;
use App\Enums\ScreenFocus;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\MapMarker;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The page on the television at the end of the table.
 *
 * One component, one audience: the party. Whoever opened it, and it is usually the
 * GM on the laptop that feeds the wall, every read here goes through a gate that
 * takes no user and no role. Combatants through visibleToPlayers(), clocks through
 * visibleTo(Player), the page through Entity::visibleToParty(), the pins through
 * MapMarker::visibleToParty(). A viewer's own role never widens what the wall says,
 * which is the whole reason it is safe to plug in.
 *
 * It listens to its own event and to the four the table already has, because a
 * handout taken back, a pin revealed, a clock ticked and a turn taken all change what
 * the wall should show. The sixty-second poll is the backstop, as everywhere else.
 *
 * Open to every member. A player at a remote table opens it on a tablet and reads
 * exactly what the wall reads.
 */
#[Layout('layouts::screen')]
class Screen extends Component
{
    use InteractsWithCampaign;

    public const POLL_SECONDS = 60;

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
    }

    #[On('echo-presence:campaign.{campaign.id},.screen.changed')]
    public function screenChanged(): void
    {
        // Deliberately empty. The re-render is the point, and it reads the columns
        // through the party's gate.
    }

    #[On('echo-presence:campaign.{campaign.id},.encounter.changed')]
    public function encounterChanged(): void
    {
        // Deliberately empty. The re-render is the point.
    }

    #[On('echo-presence:campaign.{campaign.id},.clock.changed')]
    public function clockChanged(): void
    {
        // Deliberately empty. The re-render is the point.
    }

    #[On('echo-presence:campaign.{campaign.id},.handout.revealed')]
    public function handoutRevealed(): void
    {
        // Deliberately empty. The re-render is the point.
    }

    #[On('echo-presence:campaign.{campaign.id},.map.changed')]
    public function mapChanged(): void
    {
        // Deliberately empty. The re-render is the point.
    }

    public function render(): View
    {
        $state = $this->campaign->screen();
        $focus = $state->focus;
        $page = $state->entity;

        // A focus of the fight with no fight, and no focus at all, both fall to
        // whatever is true: the fight while one runs, then the idle state.
        $fight = null;

        if ($focus === null || $focus === ScreenFocus::Fight) {
            $fight = $this->activeEncounter();
            $focus = $fight === null ? null : ScreenFocus::Fight;
        }

        $clocks = $this->campaign->clocks()->visibleTo(CampaignRole::Player)->get();

        return view('livewire.table.screen', [
            'focus' => $focus,
            'page' => $page,
            'fight' => $fight,
            'combatants' => $fight === null ? new Collection : $this->combatants($fight),
            'file' => $focus === ScreenFocus::Handout ? $page?->files()->first() : null,
            'markers' => $focus === ScreenFocus::Map && $page !== null ? $this->markers($page) : new Collection,
            'clocks' => $clocks,
            'party' => $focus === null ? $this->party() : new Collection,
            'deathSaves' => Combatant::DEATH_SAVES,
            'pollSeconds' => self::POLL_SECONDS,
        ])->title('The screen');
    }

    /**
     * The fight on the table right now, as Table\Show finds it.
     */
    private function activeEncounter(): ?Encounter
    {
        return Encounter::query()
            ->where('status', EncounterStatus::Active)
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * The party's rows and nothing else, whoever is looking. There is no GM branch.
     *
     * @return Collection<int, Combatant>
     */
    private function combatants(Encounter $encounter): Collection
    {
        return $encounter->combatants()->visibleToPlayers()->get();
    }

    /**
     * The pins the party found, with a target the whole party may see.
     *
     * @return Collection<int, MapMarker>
     */
    private function markers(Entity $map): Collection
    {
        return MapMarker::query()
            ->where('entity_id', $map->id)
            ->visibleToParty()
            ->with('target')
            ->get();
    }

    /**
     * @return Collection<int, Entity>
     */
    private function party(): Collection
    {
        return Entity::query()
            ->ofType(EntityType::Character)
            ->visibleToParty()
            ->where('is_pc', true)
            ->orderBy('name')
            ->get();
    }
}
