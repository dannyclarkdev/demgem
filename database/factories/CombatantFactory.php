<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Combatant>
 */
class CombatantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'encounter_id' => Encounter::factory(),
            'entity_id' => null,
            'name' => Str::title(fake()->word()),
            'initiative' => null,
            'initiative_bonus' => null,
            'hp' => null,
            'max_hp' => null,
            'ac' => null,
            'conditions' => [],
            'position' => 0,
            'player_visible' => false,
        ];
    }

    public function inEncounter(Encounter $encounter, int $position = 0): static
    {
        return $this->state([
            'campaign_id' => $encounter->campaign_id,
            'encounter_id' => $encounter->id,
            'position' => $position,
        ]);
    }

    public function forEntity(Entity $entity): static
    {
        return $this->state([
            'campaign_id' => $entity->campaign_id,
            'entity_id' => $entity->id,
            'name' => $entity->name,
        ]);
    }

    public function withInitiative(int $initiative): static
    {
        return $this->state(['initiative' => $initiative]);
    }

    public function withHealth(int $hp, ?int $maxHp = null): static
    {
        return $this->state(['hp' => $hp, 'max_hp' => $maxHp ?? $hp]);
    }

    /**
     * A row the GM revealed to the party. The default is hidden, which is the default
     * the column carries for everything except a player character.
     */
    public function shownToPlayers(): static
    {
        return $this->state(['player_visible' => true]);
    }
}
