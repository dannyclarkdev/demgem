<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\DowntimeActivity;
use App\Models\Entity;
use App\Models\GameSession;
use App\Support\Reckoning\GameDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DowntimeActivity>
 */
class DowntimeActivityFactory extends Factory
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
            'activity' => fake()->sentence(3),
            'days' => 1,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function forCharacter(Entity $character): static
    {
        return $this->state([
            'campaign_id' => $character->campaign_id,
            'entity_id' => $character->id,
        ]);
    }

    public function around(GameSession $session): static
    {
        return $this->state(['game_session_id' => $session->id]);
    }

    public function days(int $days): static
    {
        return $this->state(['days' => $days]);
    }

    public function startingOn(GameDate $date): static
    {
        return $this->state(['starts_on' => $date]);
    }
}
