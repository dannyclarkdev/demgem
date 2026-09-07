<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\SessionDateOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionDateOption>
 */
class SessionDateOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'game_session_id' => GameSession::factory(),
            'starts_at' => now()->addWeek()->setTime(19, 0),
            'position' => 0,
        ];
    }

    public function forSession(GameSession $session): static
    {
        return $this->state([
            'campaign_id' => $session->campaign_id,
            'game_session_id' => $session->id,
        ]);
    }
}
