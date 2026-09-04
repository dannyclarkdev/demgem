<?php

namespace App\Livewire\Clocks;

use App\Actions\Clocks\CreateClock;
use App\Actions\Clocks\DeleteClock;
use App\Actions\Clocks\TickClock;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Clock;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The clocks, drawn once for two audiences.
 *
 * A GM gets the controls; a player gets the dials the GM revealed and nothing else.
 * The role decides the query, never the template, which is the rule .ai/rules/table.md
 * recorded when Table\Fight did the same thing for combatants.
 *
 * Handed an entity id, it shows only the clocks about that entity. That is what the
 * cult's page renders, and it is the same component with one more where clause.
 *
 * It carries its own poll. A nested component does not re-render when its parent
 * polls, so the sixty seconds that cover a dropped socket everywhere else on the
 * table screen have to be asked for here too.
 */
class Panel extends Component
{
    use InteractsWithCampaign;

    public const POLL_SECONDS = 60;

    public ?string $entityId = null;

    public string $newName = '';

    public int $newSegments = Clock::DEFAULT_SEGMENTS;

    public function mount(Campaign $campaign, ?string $entityId = null): void
    {
        // Nested and it writes, so it re-checks membership itself on every round trip.
        $this->enterCampaign($campaign);

        $this->entityId = $entityId;
    }

    /**
     * A clock moved somewhere. The re-render picks it up; nothing is read from the
     * payload, and it runs under this viewer's own role.
     */
    #[On('echo-presence:campaign.{campaign.id},.clock.changed')]
    public function clockChanged(): void
    {
        // Deliberately empty. The re-render is the point.
    }

    public function create(CreateClock $createClock): void
    {
        $this->authorize('create', [Clock::class, $this->campaign]);

        $validated = $this->validate([
            'newName' => ['required', 'string', 'max:'.Clock::MAX_NAME_LENGTH],
            'newSegments' => ['required', 'integer', 'in:'.implode(',', Clock::SIZES)],
        ]);

        $createClock->handle(
            $this->campaign,
            $validated['newName'],
            $validated['newSegments'],
            $this->entity(),
        );

        $this->reset('newName');
    }

    /**
     * A click on a wedge. The value is clamped in the action, because it arrives from
     * a browser the way a pin's coordinate does.
     */
    public function setTo(string $clockId, int $filled, TickClock $tickClock): void
    {
        $clock = $this->clock($clockId);

        $this->authorize('update', $clock);

        $tickClock->to($clock, $filled);
    }

    /**
     * The plus and the minus. They are the keyboard and screen reader path to the
     * dial, so they are real buttons and they never disappear.
     */
    public function tick(string $clockId, int $delta, TickClock $tickClock): void
    {
        $clock = $this->clock($clockId);

        $this->authorize('update', $clock);

        $tickClock->by($clock, $delta);
    }

    public function delete(string $clockId, DeleteClock $deleteClock): void
    {
        $clock = $this->clock($clockId);

        $this->authorize('delete', $clock);

        $deleteClock->handle($clock);
    }

    public function render(): View
    {
        $clocks = $this->clocks();

        return view('livewire.clocks.panel', [
            'clocks' => $clocks,
            'links' => $this->links($clocks),
            'canManage' => $this->role()->isDm(),
            'sizes' => Clock::SIZES,
            'pollSeconds' => self::POLL_SECONDS,
        ]);
    }

    /**
     * @return Collection<int, Clock>
     */
    private function clocks(): Collection
    {
        return Clock::query()
            ->visibleTo($this->role())
            ->when($this->entityId !== null, fn (Builder $query) => $query->where('entity_id', $this->entityId))
            ->orderBy('position')
            ->get();
    }

    /**
     * What each clock is about, keyed by entity id, and only the ones this viewer may
     * see. The link is gated separately from the row: a clock whose entity is hidden
     * still shows, and loses its link, because a GM who revealed "The Duke's suspicion"
     * meant the party to read those words. See Clock::scopeVisibleTo().
     *
     * It is one query for the whole panel, not one per row, and the filter is in that
     * query rather than in the Blade, which is what .ai/rules/table.md asks for.
     *
     * @param  Collection<int, Clock>  $clocks
     * @return Collection<string, Entity>
     */
    private function links(Collection $clocks): Collection
    {
        /** @var Collection<int, string> $ids */
        $ids = $clocks->pluck('entity_id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            /** @var Collection<string, Entity> $empty */
            $empty = new Collection;

            return $empty;
        }

        /** @var User $user */
        $user = auth()->user();

        return Entity::query()
            ->visibleTo($user, $this->role())
            ->whereKey($ids->all())
            ->get()
            ->keyBy('id');
    }

    private function clock(string $clockId): Clock
    {
        /** @var Clock $clock */
        $clock = Clock::query()->whereKey($clockId)->firstOrFail();

        return $clock;
    }

    private function entity(): ?Entity
    {
        if ($this->entityId === null) {
            return null;
        }

        return Entity::query()->whereKey($this->entityId)->first();
    }
}
