<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Entity;
use App\Models\MapMarker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MapMarker>
 */
class MapMarkerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'entity_id' => Entity::factory(),
            'target_entity_id' => null,
            'label' => Str::title(fake()->words(2, true)),
            'x' => fake()->randomFloat(3, 0, 100),
            'y' => fake()->randomFloat(3, 0, 100),
            'player_visible' => false,
        ];
    }

    public function onMap(Entity $map): static
    {
        return $this->state([
            'campaign_id' => $map->campaign_id,
            'entity_id' => $map->id,
        ]);
    }

    public function pointingAt(Entity $target): static
    {
        return $this->state([
            'target_entity_id' => $target->id,
            'label' => $target->name,
        ]);
    }

    public function at(float $x, float $y): static
    {
        return $this->state(['x' => $x, 'y' => $y]);
    }

    /**
     * A pin the GM revealed. The default is hidden, which is the default the column
     * carries: the party has not found it yet.
     */
    public function shownToPlayers(): static
    {
        return $this->state(['player_visible' => true]);
    }
}
