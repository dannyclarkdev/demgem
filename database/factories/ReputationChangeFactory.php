<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\ReputationChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReputationChange>
 */
class ReputationChangeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'entity_id' => Entity::factory(),
            'game_session_id' => null,
            'delta' => 1,
            'reason' => null,
            'player_visible' => false,
            'created_by' => null,
        ];
    }

    public function about(Entity $faction): static
    {
        return $this->state(['campaign_id' => $faction->campaign_id, 'entity_id' => $faction->id]);
    }

    public function by(int $delta, ?string $reason = null): static
    {
        return $this->state(['delta' => $delta, 'reason' => $reason]);
    }

    public function madeIn(GameSession $session): static
    {
        return $this->state(['game_session_id' => $session->id]);
    }

    public function shownToPlayers(): static
    {
        return $this->state(['player_visible' => true]);
    }
}
