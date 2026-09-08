<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Entity;
use App\Models\EntityRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntityRelation>
 */
class EntityRelationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'entity_id' => Entity::factory(),
            'target_entity_id' => Entity::factory(),
            'label' => 'ally of',
            'reverse_label' => null,
            'player_visible' => false,
            'position' => 0,
        ];
    }

    public function between(Entity $source, Entity $target): static
    {
        return $this->state([
            'campaign_id' => $source->campaign_id,
            'entity_id' => $source->id,
            'target_entity_id' => $target->id,
        ]);
    }

    public function shownToPlayers(): static
    {
        return $this->state(['player_visible' => true]);
    }
}
