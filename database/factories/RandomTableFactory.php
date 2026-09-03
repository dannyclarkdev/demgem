<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\RandomTable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RandomTable>
 */
class RandomTableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'name' => Str::title(fake()->unique()->words(2, true)),
            'description' => null,
        ];
    }

    /**
     * @param  list<string>  $bodies
     */
    public function withEntries(array $bodies, int $weight = 1): static
    {
        return $this->afterCreating(function (RandomTable $table) use ($bodies, $weight): void {
            foreach ($bodies as $position => $body) {
                RandomTableEntryFactory::new()->create([
                    'campaign_id' => $table->campaign_id,
                    'random_table_id' => $table->id,
                    'position' => $position,
                    'weight' => $weight,
                    'body' => $body,
                ]);
            }
        });
    }
}
