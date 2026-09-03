<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\QuestObjective;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuestObjective>
 */
class QuestObjectiveFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'entity_id' => Entity::factory()->quest(),
            'position' => 0,
            'body' => Str::ucfirst(fake()->words(5, true)),
            'completed_at' => null,
            'completed_in_session_id' => null,
        ];
    }

    public function forQuest(Entity $quest, int $position = 0): static
    {
        return $this->state([
            'campaign_id' => $quest->campaign_id,
            'entity_id' => $quest->id,
            'position' => $position,
        ]);
    }

    public function complete(): static
    {
        return $this->state(['completed_at' => now()]);
    }

    public function completedIn(GameSession $session): static
    {
        return $this->state([
            'completed_at' => now(),
            'completed_in_session_id' => $session->id,
        ]);
    }
}
