<?php

namespace App\Livewire\Encounters;

use App\Actions\Encounters\CreateEncounter;
use App\Actions\Encounters\DeleteEncounter;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Encounter;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Every fight in the campaign, in play first. GM-only; the route 404s for a player.
 */
class Index extends Component
{
    use InteractsWithCampaign;

    public string $newName = '';

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('viewAny', [Encounter::class, $campaign]);
    }

    public function create(CreateEncounter $createEncounter): void
    {
        $this->authorize('create', [Encounter::class, $this->campaign]);

        $validated = $this->validate(['newName' => ['required', 'string', 'max:120']]);

        $encounter = $createEncounter->handle($this->campaign, $this->user(), $validated['newName']);

        $this->redirect($encounter->url());
    }

    public function delete(string $encounterId, DeleteEncounter $deleteEncounter): void
    {
        $encounter = Encounter::query()->whereKey($encounterId)->firstOrFail();

        $this->authorize('delete', $encounter);

        $deleteEncounter->handle($encounter);

        session()->flash('status', "{$encounter->name} was deleted.");
    }

    public function render(): View
    {
        return view('livewire.encounters.index', [
            'encounters' => $this->encounters(),
        ])->title('Encounters');
    }

    /**
     * @return Collection<int, Encounter>
     */
    private function encounters(): Collection
    {
        return Encounter::query()
            ->with('gameSession')
            ->withCount('combatants')
            ->latest()
            ->get()
            ->sortBy([
                fn (Encounter $a, Encounter $b) => $a->status->weight() <=> $b->status->weight(),
                fn (Encounter $a, Encounter $b) => $b->created_at <=> $a->created_at,
            ])
            ->values();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
