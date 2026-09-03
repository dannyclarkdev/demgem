<?php

namespace App\Livewire\Maps;

use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The map, and the pins on it.
 *
 * The component renders; it does not pan. Panning and zooming are Alpine state and a
 * CSS transform, and they never reach the server: they are the two things a person
 * does constantly, and a round trip for either would make the map feel like a website
 * instead of a map. The server hears about placing, moving and revealing a pin, and
 * each of those is one deliberate click.
 *
 * It holds the map's id rather than the map, and reads the row each render. The same
 * reasoning as Table\Fight: a map open on a player's tablet for a whole session must
 * survive the GM deleting it, and an id renders "that map is gone" where a model
 * property fails to hydrate.
 *
 * Nested, so it calls enterCampaign() in its own mount: the hydrate hook runs per
 * component, and a member removed mid-session must stop reading the world.
 */
class Viewer extends Component
{
    use InteractsWithCampaign;

    public string $mapId = '';

    public function mount(Campaign $campaign, string $mapId): void
    {
        $this->enterCampaign($campaign);

        $map = $this->map($mapId);

        abort_if($map === null, 404);

        $this->authorize('view', $map);

        $this->mapId = $map->id;
    }

    public function render(): View
    {
        $map = $this->map($this->mapId);

        return view('livewire.maps.viewer', [
            'map' => $map,
            'imageUrl' => $map?->imageUrl(),
        ]);
    }

    /**
     * Scoped to the campaign by the global scope, and to the viewer by visibleTo(),
     * so a map the person may not read never resolves at all.
     */
    private function map(string $mapId): ?Entity
    {
        return Entity::query()
            ->visibleTo($this->user(), $this->role())
            ->whereKey($mapId)
            ->first();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
