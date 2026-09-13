<?php

namespace App\Livewire\Table;

use App\Actions\Table\SetScreen;
use App\Enums\CampaignRole;
use App\Enums\EncounterStatus;
use App\Enums\EntityType;
use App\Enums\ScreenFocus;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The GM's card on the Run screen: what the wall shows, and the buttons that change
 * it. Every write goes through SetScreen and is gated by useGmTools, the gate the
 * tracker and the tables already use.
 *
 * The handouts offered are every one the GM has, because putting a hidden one up
 * reveals it on the way. The maps offered are the ones the party may already see,
 * with a picture, because a map is not revealed on the way and a map with no picture
 * is a blank wall.
 *
 * Nested and it writes, so it re-checks membership itself on every round trip.
 */
class ScreenControls extends Component
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
        // Deliberately empty. A co-GM on a second device changed it; re-render.
    }

    #[On('echo-presence:campaign.{campaign.id},.handout.revealed')]
    public function handoutRevealed(): void
    {
        // Deliberately empty. The labels say which handouts are hidden.
    }

    #[On('echo-presence:campaign.{campaign.id},.encounter.changed')]
    public function encounterChanged(): void
    {
        // Deliberately empty. Whether "The fight" is offered depends on it.
    }

    /**
     * A handout or a map. A hidden map is a 404 here, the way a hidden page is
     * everywhere: the query never offered it, so a posted id for one is a guess.
     */
    public function showPage(string $pageId, SetScreen $setScreen): void
    {
        $this->authorize('useGmTools', $this->campaign);

        $page = Entity::query()
            ->whereKey($pageId)
            ->where(function (Builder $query): void {
                $query->where(fn (Builder $handout) => $handout->ofType(EntityType::Handout))
                    ->orWhere(fn (Builder $map) => $map->ofType(EntityType::Map)->visibleToParty());
            })
            ->first();

        abort_if($page === null, 404);

        $setScreen->show($this->campaign, $page, $this->user());
    }

    public function focus(string $focus, SetScreen $setScreen): void
    {
        $this->authorize('useGmTools', $this->campaign);

        $case = ScreenFocus::tryFrom($focus);

        abort_if($case === null || $case->needsPage(), 404);

        $setScreen->focus($this->campaign, $case);
    }

    public function clear(SetScreen $setScreen): void
    {
        $this->authorize('useGmTools', $this->campaign);

        $setScreen->focus($this->campaign, null);
    }

    public function render(): View
    {
        $canManage = $this->isDm();
        $state = $this->campaign->screen();

        return view('livewire.table.screen-controls', [
            'canManage' => $canManage,
            'focus' => $state->focus,
            'page' => $state->entity,
            'handouts' => $canManage ? $this->handouts() : new Collection,
            'maps' => $canManage ? $this->maps() : new Collection,
            'hasFight' => Encounter::query()->where('status', EncounterStatus::Active)->exists(),
            'hasClocks' => $this->campaign->clocks()->visibleTo(CampaignRole::Player)->exists(),
            // The backstop the panels keep: a handout taken back from the panel beside
            // this card changes what this card says, and without a socket the poll
            // is what catches it.
            'pollSeconds' => self::POLL_SECONDS,
        ]);
    }

    /**
     * @return Collection<int, Entity>
     */
    private function handouts(): Collection
    {
        return Entity::query()->ofType(EntityType::Handout)->orderBy('name')->get();
    }

    /**
     * The maps with a picture the party may see. The picture check is in PHP over a
     * short list: a campaign has a handful of maps, not a thousand.
     *
     * @return Collection<int, Entity>
     */
    private function maps(): Collection
    {
        return Entity::query()
            ->ofType(EntityType::Map)
            ->visibleToParty()
            ->with('media')
            ->orderBy('name')
            ->get()
            ->filter(fn (Entity $map) => $map->imageUrl() !== null)
            ->values();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
