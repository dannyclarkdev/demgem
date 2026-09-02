<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word());

        return [
            'campaign_id' => Campaign::factory(),
            'name' => $name,
            'slug' => fn (array $attributes) => Str::slug($attributes['name']),
            'color' => null,
        ];
    }
}
