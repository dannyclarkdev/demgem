<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Scene>
 */
class SceneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'game_session_id' => GameSession::factory(),
            'position' => 0,
            'title' => Str::title(fake()->words(3, true)),
            'notes' => fake()->sentence(),
        ];
    }

    public function inSession(GameSession $session, int $position = 0): static
    {
        return $this->state([
            'campaign_id' => $session->campaign_id,
            'game_session_id' => $session->id,
            'position' => $position,
        ]);
    }

    public function withNotes(string $notes): static
    {
        return $this->state(['notes' => $notes]);
    }
}
