<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RandomTableEntry>
 */
class RandomTableEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'random_table_id' => RandomTable::factory(),
            'position' => 0,
            'weight' => 1,
            'body' => Str::ucfirst(fake()->words(4, true)),
            'nested_table_id' => null,
        ];
    }

    public function inTable(RandomTable $table, int $position = 0): static
    {
        return $this->state([
            'campaign_id' => $table->campaign_id,
            'random_table_id' => $table->id,
            'position' => $position,
        ]);
    }

    public function weighing(int $weight): static
    {
        return $this->state(['weight' => $weight]);
    }

    public function nesting(RandomTable $table): static
    {
        return $this->state(['nested_table_id' => $table->id]);
    }
}
