<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Somebody rolled. Not what, not who, and not the result.
 *
 * One ULID, and the campaign's own. Every listener is a Livewire component that
 * re-renders on the server under its own viewer's identity, so DiceRoll::visibleTo()
 * decides per screen what the log holds. A private roll therefore broadcasts exactly
 * like a public one, and everyone but the person who made it re-renders and sees
 * nothing new. There is no payload to leak and no second channel to keep in step.
 *
 * ShouldRescue, because a player rolling a d20 must never see an error from a
 * websocket server that happens to be down. The sixty-second poll is the backstop.
 */
class DiceRolled implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly string $campaignId) {}

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel('campaign.'.$this->campaignId)];
    }

    public function broadcastAs(): string
    {
        return 'dice.rolled';
    }

    /**
     * @return array{}
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
