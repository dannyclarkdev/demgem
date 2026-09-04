<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Clock;
use App\Models\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Clock>
 */
class ClockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'entity_id' => null,
            'name' => Str::title(fake()->words(3, true)),
            'segments' => Clock::DEFAULT_SEGMENTS,
            'filled' => 0,
            'player_visible' => false,
            'position' => 0,
        ];
    }

    public function inCampaign(Campaign $campaign): static
    {
        return $this->state(['campaign_id' => $campaign->id]);
    }

    public function about(Entity $entity): static
    {
        return $this->state([
            'campaign_id' => $entity->campaign_id,
            'entity_id' => $entity->id,
        ]);
    }

    /**
     * @param  int<2, 12>  $segments
     */
    public function sized(int $segments): static
    {
        return $this->state(['segments' => $segments]);
    }

    public function filled(int $filled): static
    {
        return $this->state(['filled' => $filled]);
    }

    /**
     * A clock the GM revealed. The default is hidden, which is the default the column
     * carries: the party has not been told this one is ticking.
     */
    public function shownToPlayers(): static
    {
        return $this->state(['player_visible' => true]);
    }
}
