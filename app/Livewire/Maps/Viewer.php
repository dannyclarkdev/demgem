<?php

namespace App\Livewire\Maps;

use App\Actions\Maps\MoveMarker;
use App\Actions\Maps\PlaceMarker;
use App\Actions\Maps\RemoveMarker;
use App\Actions\Maps\SetMarkerVisibility;
use App\Actions\Maps\UpdateMarker;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\MapMarker;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
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

    /** The pin the GM is naming, or null. */
    public ?string $editing = null;

    public string $label = '';

    public ?string $targetId = null;

    public function mount(Campaign $campaign, string $mapId): void
    {
        $this->enterCampaign($campaign);

        $map = $this->map($mapId);

        abort_if($map === null, 404);

        $this->authorize('view', $map);

        $this->mapId = $map->id;
    }

    /**
     * Somebody changed this map. The re-render is the whole listener, and it runs
     * under this viewer's own role, so a pin the party has not found stays unfound.
     *
     * There is no poll behind this. A map is not a turn order: a dropped socket costs
     * a refresh, not a wrong answer about whose turn it is.
     *
     * @param  array{mapId?: string}  $event
     */
    #[On('echo-presence:campaign.{campaign.id},.map.changed')]
    public function mapChanged(array $event): void
    {
        if (($event['mapId'] ?? null) !== $this->mapId) {
            $this->skipRender();
        }
    }

    /**
     * A click on the image, in percentages. Alpine works out the arithmetic, because
     * the browser is the only thing that knows the rendered size, and the action
     * clamps the answer because a browser sends whatever it likes.
     */
    public function placeMarker(float $x, float $y, PlaceMarker $placeMarker): void
    {
        $map = $this->editableMap();

        $marker = $placeMarker->handle($map, $x, $y);

        $this->openMarker($marker->id);
    }

    public function moveMarker(string $markerId, float $x, float $y, MoveMarker $moveMarker): void
    {
        $this->editableMap();

        $moveMarker->handle($this->marker($markerId), $x, $y);
    }

    public function openMarker(string $markerId): void
    {
        $this->editableMap();

        $marker = $this->marker($markerId);

        $this->editing = $marker->id;
        $this->label = $marker->label;
        $this->targetId = $marker->target_entity_id;
    }

    public function closeMarker(): void
    {
        $this->reset(['editing', 'label', 'targetId']);
    }

    public function saveMarker(UpdateMarker $updateMarker): void
    {
        $this->editableMap();

        $validated = $this->validate([
            'label' => ['required', 'string', 'max:'.MapMarker::MAX_LABEL_LENGTH],
            'targetId' => ['nullable', 'string'],
        ]);

        $target = $validated['targetId'] === null
            ? null
            : Entity::query()->whereKey($validated['targetId'])->first();

        $updateMarker->handle(
            $this->marker((string) $this->editing),
            $validated['label'],
            $target,
            clearTarget: $target === null,
        );

        $this->closeMarker();
    }

    public function removeMarker(string $markerId, RemoveMarker $removeMarker): void
    {
        $this->editableMap();

        $removeMarker->handle($this->marker($markerId));

        $this->closeMarker();
    }

    public function toggleVisibility(string $markerId, SetMarkerVisibility $setVisibility): void
    {
        $this->editableMap();

        $setVisibility->toggle($this->marker($markerId));
    }

    public function setAllVisibility(bool $visible, SetMarkerVisibility $setVisibility): void
    {
        $setVisibility->setAll($this->editableMap(), $visible);
    }

    public function render(): View
    {
        $map = $this->map($this->mapId);

        return view('livewire.maps.viewer', [
            'map' => $map,
            'imageUrl' => $map?->imageUrl(),
            'markers' => $map === null ? new Collection : $this->markers($map),
            'isDm' => $this->isDm(),
            // Only while a pin is open, so the common render costs nothing for it.
            // Queries stay out of the Blade, as SidebarComposer's docblock asks.
            'targetOptions' => $this->editing === null ? new Collection : $this->targetOptions(),
        ]);
    }

    /**
     * What a pin may point at: anything in the campaign, this map included, because a
     * map that pins itself is silly rather than dangerous.
     *
     * @return Collection<int, Entity>
     */
    private function targetOptions(): Collection
    {
        return Entity::query()
            ->visibleTo($this->user(), $this->role())
            ->orderBy('name')
            ->get(['id', 'name', 'type']);
    }

    /**
     * One eager-loaded query, whatever the map holds. The filter is in the query and
     * not in the template, so a pin the party has not found is never loaded at all.
     *
     * @return Collection<int, MapMarker>
     */
    private function markers(Entity $map): Collection
    {
        return $map->markers()
            ->visibleTo($this->user(), $this->role())
            ->with('target')
            ->orderBy('label')
            ->get();
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

    /**
     * Every write goes through here first. Markers have no policy of their own: they
     * authorize through update() on the map, the way combatants do through the
     * encounter that owns them.
     */
    private function editableMap(): Entity
    {
        $map = $this->map($this->mapId);

        abort_if($map === null, 404);

        $this->authorize('update', $map);

        return $map;
    }

    /**
     * Scoped to this map, so a pin id from another map or another campaign is a 404
     * rather than a pin somebody else owns.
     */
    private function marker(string $markerId): MapMarker
    {
        $marker = MapMarker::query()
            ->where('entity_id', $this->mapId)
            ->whereKey($markerId)
            ->first();

        abort_if($marker === null, 404);

        return $marker;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
