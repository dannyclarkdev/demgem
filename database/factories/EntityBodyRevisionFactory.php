<?php

namespace Database\Factories;

use App\Models\Entity;
use App\Models\EntityBodyRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EntityBodyRevision> */
class EntityBodyRevisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entity_id' => Entity::factory(),
            'campaign_id' => fn (array $attributes) => Entity::withoutGlobalScopes()->findOrFail($attributes['entity_id'])->campaign_id,
            'body' => fake()->paragraph(),
            'replaced_by' => null,
            'replaced_by_name' => null,
            'recorded_at' => now(),
        ];
    }
}
