<?php

namespace App\Livewire\Clocks;

use App\Actions\Clocks\CreateClock;
use App\Actions\Clocks\DeleteClock;
use App\Actions\Clocks\ReorderClocks;
use App\Actions\Clocks\SetClockVisibility;
use App\Actions\Clocks\TickClock;
use App\Actions\Clocks\UpdateClock;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Clock;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Every clock in the campaign, with the parts a GM does between sessions: rename,
 * resize, reorder, delete. GM-only; the route 404s for a player.
 *
 * Ticking lives here as well, because a GM who is looking at the list is the GM who
 * wants to move one. The panel on the Run screen is the same actions in less space.
 */
class Index extends Component
{
    use InteractsWithCampaign;

    public string $newName = '';

    public int $newSegments = Clock::DEFAULT_SEGMENTS;

    public ?string $editingId = null;

    public string $editingName = '';

    public int $editingSegments = Clock::DEFAULT_SEGMENTS;

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('viewAny', [Clock::class, $campaign]);
    }

    #[On('echo-presence:campaign.{campaign.id},.clock.changed')]
    public function clockChanged(): void
    {
        // Deliberately empty. A second GM moved something; the re-render is the point.
    }

    public function create(CreateClock $createClock): void
    {
        $this->authorize('create', [Clock::class, $this->campaign]);

        $validated = $this->validate([
            'newName' => ['required', 'string', 'max:'.Clock::MAX_NAME_LENGTH],
            'newSegments' => ['required', 'integer', 'in:'.implode(',', Clock::SIZES)],
        ]);

        $createClock->handle($this->campaign, $validated['newName'], $validated['newSegments']);

        $this->reset('newName');
    }

    public function edit(string $clockId): void
    {
        $clock = $this->clock($clockId);

        $this->authorize('update', $clock);

        $this->editingId = $clock->id;
        $this->editingName = $clock->name;
        $this->editingSegments = $clock->segments;
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editingName', 'editingSegments');
    }

    public function save(UpdateClock $updateClock): void
    {
        $clock = $this->clock((string) $this->editingId);

        $this->authorize('update', $clock);

        $validated = $this->validate([
            'editingName' => ['required', 'string', 'max:'.Clock::MAX_NAME_LENGTH],
            'editingSegments' => ['required', 'integer', 'in:'.implode(',', Clock::SIZES)],
        ]);

        // A dial that shrinks below its fill would read "8 of 6", so UpdateClock brings
        // the fill down with it. Growing one leaves the fill where it is.
        $updateClock->handle($clock, $validated['editingName'], $validated['editingSegments'], $clock->entity);

        $this->cancelEdit();
    }

    public function setTo(string $clockId, int $filled, TickClock $tickClock): void
    {
        $clock = $this->clock($clockId);

        $this->authorize('update', $clock);

        $tickClock->to($clock, $filled);
    }

    public function tick(string $clockId, int $delta, TickClock $tickClock): void
    {
        $clock = $this->clock($clockId);

        $this->authorize('update', $clock);

        $tickClock->by($clock, $delta);
    }

    /**
     * Drag and drop. Livewire hands us the item id and its new zero-based position.
     */
    public function reorder(string $clockId, int $position, ReorderClocks $reorderClocks): void
    {
        $this->authorize('update', $this->clock($clockId));

        $reorderClocks->handle($this->campaign, $clockId, $position);
    }

    /**
     * The keyboard and tablet path to the same place.
     */
    public function move(string $clockId, int $offset, ReorderClocks $reorderClocks): void
    {
        $clock = $this->clock($clockId);

        $this->authorize('update', $clock);

        $reorderClocks->move($this->campaign, $clock, $offset);
    }

    public function toggleVisibility(string $clockId, SetClockVisibility $setClockVisibility): void
    {
        $clock = $this->clock($clockId);

        $this->authorize('update', $clock);

        $setClockVisibility->toggle($clock);
    }

    public function delete(string $clockId, DeleteClock $deleteClock): void
    {
        $clock = $this->clock($clockId);

        $this->authorize('delete', $clock);

        $deleteClock->handle($clock);

        if ($this->editingId === $clockId) {
            $this->cancelEdit();
        }

        session()->flash('status', "{$clock->name} was deleted.");
    }

    public function render(): View
    {
        return view('livewire.clocks.index', [
            'clocks' => Clock::query()->with('entity')->orderBy('position')->get(),
            'sizes' => Clock::SIZES,
        ])->title('Clocks');
    }

    private function clock(string $clockId): Clock
    {
        /** @var Clock $clock */
        $clock = Clock::query()->whereKey($clockId)->firstOrFail();

        return $clock;
    }
}
