<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something in a fight changed. Not what, and not to what.
 *
 * The payload is two ULIDs on purpose. Every listener is a Livewire component that
 * re-renders on the server under its own viewer's role, so the visibility rules that
 * already guard every request guard this too. There is no payload to leak, which is
 * a stronger promise than a carefully filtered one.
 *
 * ShouldRescue, because a GM clicking "next turn" must never see an error from a
 * websocket server that happens to be down. The sixty-second poll is the backstop.
 */
class EncounterChanged implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $campaignId,
        public readonly string $encounterId,
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
        return 'encounter.changed';
    }

    /**
     * @return array{encounterId: string}
     */
    public function broadcastWith(): array
    {
        return ['encounterId' => $this->encounterId];
    }
}
