<?php

namespace Database\Factories;

use App\Enums\EncounterStatus;
use App\Models\Campaign;
use App\Models\Encounter;
use App\Models\GameSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Encounter>
 */
class EncounterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'game_session_id' => null,
            'name' => Str::title(fake()->words(2, true)),
            'status' => EncounterStatus::Planning,
            'round' => 0,
            'active_combatant_id' => null,
        ];
    }

    public function status(EncounterStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function active(int $round = 1): static
    {
        return $this->state(['status' => EncounterStatus::Active, 'round' => $round]);
    }

    public function done(): static
    {
        return $this->state(['status' => EncounterStatus::Done]);
    }

    public function inSession(GameSession $session): static
    {
        return $this->state([
            'campaign_id' => $session->campaign_id,
            'game_session_id' => $session->id,
        ]);
    }
}
