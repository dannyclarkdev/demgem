<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\Secret;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Secret>
 */
class SecretFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'game_session_id' => null,
            'body' => fake()->sentence(),
            'position' => 0,
            'revealed_at' => null,
            'revealed_in_session_id' => null,
        ];
    }

    public function preparedFor(GameSession $session, int $position = 0): static
    {
        return $this->state([
            'campaign_id' => $session->campaign_id,
            'game_session_id' => $session->id,
            'position' => $position,
        ]);
    }

    /**
     * In the pool: written for the campaign, not yet pinned to a session.
     */
    public function pooled(): static
    {
        return $this->state(['game_session_id' => null]);
    }

    public function revealedIn(GameSession $session): static
    {
        return $this->state([
            'revealed_at' => now(),
            'revealed_in_session_id' => $session->id,
        ]);
    }
}
