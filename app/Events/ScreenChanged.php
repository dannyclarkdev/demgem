<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The GM changed what the screen shows. Not to what.
 *
 * One ULID, and it is in the channel name already, so the payload is empty. Every
 * listener is a Livewire component that re-renders on the server and reads the two
 * columns through the party's gate, so a GM who put up a page the party may not see
 * has changed nothing on the wall. There is no payload to filter and none to leak.
 *
 * ShouldRescue, because a GM pressing "On the screen" must never see an error from a
 * websocket server that happens to be down. The screen's own sixty-second poll is the
 * backstop.
 */
class ScreenChanged implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $campaignId,
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
        return 'screen.changed';
    }

    /**
     * @return array{}
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
