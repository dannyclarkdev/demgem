<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Decision;
use App\Models\GameSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Decision>
 */
class DecisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'game_session_id' => null,
            'choice' => fake()->sentence(),
            'consequence' => null,
            'player_visible' => false,
            'created_by' => null,
        ];
    }

    public function inCampaign(Campaign $campaign): static
    {
        return $this->state(['campaign_id' => $campaign->id]);
    }

    public function madeIn(GameSession $session): static
    {
        return $this->state([
            'campaign_id' => $session->campaign_id,
            'game_session_id' => $session->id,
        ]);
    }

    public function withConsequence(string $consequence): static
    {
        return $this->state(['consequence' => $consequence]);
    }

    /**
     * A decision the GM revealed. The default is hidden, like a clock's.
     */
    public function shownToPlayers(): static
    {
        return $this->state(['player_visible' => true]);
    }
}
