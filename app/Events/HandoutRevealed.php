<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A handout changed hands. Not which way, and not what is on it.
 *
 * Two ULIDs, as EncounterChanged, MapChanged and ClockChanged carry two. Every
 * listener is a Livewire component that re-renders on the server under its own
 * viewer's role, so Entity::visibleTo() decides per screen what the table holds.
 * Showing the party a letter and taking it back therefore broadcast exactly the same
 * two ids, and a player whose screen re-renders after a change they may not see finds
 * nothing new.
 *
 * ShouldRescue, because a GM dropping the letter on the table must never see an error
 * from a websocket server that happens to be down. The table screen's own sixty-second
 * poll is the backstop.
 */
class HandoutRevealed implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $campaignId,
        public readonly string $handoutId,
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
        return 'handout.revealed';
    }

    /**
     * @return array{handoutId: string}
     */
    public function broadcastWith(): array
    {
        return ['handoutId' => $this->handoutId];
    }
}
