<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A pin moved, appeared, or was revealed. Not which, and not to what.
 *
 * Two ULIDs, as EncounterChanged carries two. Every listener is a Livewire component
 * that re-renders on the server under its own viewer's role, so MapMarker::visibleTo()
 * decides per screen what the map holds. A pin revealed and a pin hidden therefore
 * broadcast exactly the same two ids, and a player whose screen re-renders after a
 * change they may not see finds nothing new.
 *
 * ShouldRescue, because a GM revealing a door must never see an error from a
 * websocket server that happens to be down. There is no poll behind this one: a map
 * is not a turn order, and a refresh is a fair price for a dropped socket.
 */
class MapChanged implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $campaignId,
        public readonly string $mapId,
    ) {}

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel('campaign.'.$this->campaignId)];
    }

    public function broadcastAs(): string
    {
        return 'map.changed';
    }

    /**
     * @return array{mapId: string}
     */
    public function broadcastWith(): array
    {
        return ['mapId' => $this->mapId];
    }
}
