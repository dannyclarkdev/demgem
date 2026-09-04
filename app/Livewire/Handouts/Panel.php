<?php

namespace App\Livewire\Handouts;

use App\Actions\Handouts\RevealHandout;
use App\Enums\EntityType;
use App\Enums\Visibility;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The handouts, drawn once for two audiences.
 *
 * A GM gets every one and a button to drop it on the table; a player gets the ones
 * the GM handed over. The role decides the query, never the template, which is the
 * rule .ai/rules/table.md recorded when Table\Fight did the same for combatants.
 * Entity::visibleTo() is the whole filter, because the reveal is the visibility
 * column and there is no second switch to get wrong.
 *
 * Newest first and capped: a campaign with a year of letters in it should not put all
 * of them on the screen a player keeps open during a game.
 *
 * It carries its own poll, because a nested component does not re-render when its
 * parent polls.
 */
class Panel extends Component
{
    use InteractsWithCampaign;

    public const POLL_SECONDS = 60;

    public const LIMIT = 10;

    public function mount(Campaign $campaign): void
    {
        // Nested and it writes, so it re-checks membership itself on every round trip.
        $this->enterCampaign($campaign);
    }

    /**
     * A handout changed hands. The re-render picks it up, under this viewer's role.
     */
    #[On('echo-presence:campaign.{campaign.id},.handout.revealed')]
    public function handoutRevealed(): void
    {
        // Deliberately empty. The re-render is the point.
    }

    public function reveal(string $handoutId, RevealHandout $revealHandout): void
    {
        $handout = $this->handout($handoutId);

        $this->authorize('update', $handout);

        $revealHandout->show($handout, $this->user());
    }

    public function takeBack(string $handoutId, RevealHandout $revealHandout): void
    {
        $handout = $this->handout($handoutId);

        $this->authorize('update', $handout);

        $revealHandout->takeBack($handout, $this->user());
    }

    public function render(): View
    {
        return view('livewire.handouts.panel', [
            'handouts' => $this->handouts(),
            'canManage' => $this->role()->isDm(),
            'pollSeconds' => self::POLL_SECONDS,
        ]);
    }

    /**
     * @return Collection<int, Entity>
     */
    private function handouts(): Collection
    {
        return Entity::query()
            ->ofType(EntityType::Handout)
            ->visibleTo($this->user(), $this->role())
            ->with('media')
            ->latest('updated_at')
            ->limit(self::LIMIT)
            ->get();
    }

    private function handout(string $handoutId): Entity
    {
        /** @var Entity $handout */
        $handout = Entity::query()->ofType(EntityType::Handout)->whereKey($handoutId)->firstOrFail();

        return $handout;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
