<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A clock moved. Not which way, and not by how much.
 *
 * Two ULIDs, as EncounterChanged and MapChanged carry two. Every listener is a
 * Livewire component that re-renders on the server under its own viewer's role, so
 * Clock::visibleTo() decides per screen what the panel holds. A clock ticked, a clock
 * renamed, a clock revealed and a clock hidden therefore broadcast exactly the same
 * two ids, and a player whose screen re-renders after a change they may not see finds
 * nothing new. There is no payload to filter and none to leak.
 *
 * ShouldRescue, because a GM filling a wedge must never see an error from a websocket
 * server that happens to be down. The panel's own sixty-second poll is the backstop:
 * a clock is closer to a turn order than to a map, and a table watching a countdown
 * should not have to refresh to find out it moved.
 */
class ClockChanged implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $campaignId,
        public readonly string $clockId,
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
        return 'clock.changed';
    }

    /**
     * @return array{clockId: string}
     */
    public function broadcastWith(): array
    {
        return ['clockId' => $this->clockId];
    }
}
