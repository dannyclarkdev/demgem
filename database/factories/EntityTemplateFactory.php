<?php

namespace Database\Factories;

use App\Enums\EntityType;
use App\Models\Campaign;
use App\Models\EntityTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EntityTemplate> */
class EntityTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'type' => EntityType::Character,
            'name' => fake()->words(3, true),
            'body' => "## Appearance\n\n## Wants\n\n## Knows\n",
        ];
    }
}
