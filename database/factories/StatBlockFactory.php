<?php

namespace Database\Factories;

use App\Models\StatBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatBlock>
 */
class StatBlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(2, true));

        return [
            'ruleset' => 'srd-5e-2024',
            'slug' => str($name)->slug()->value(),
            'name' => $name,
            'source' => 'SRD 5.2.1',
            'license' => 'CC-BY-4.0',
            'type_line' => 'Medium Humanoid, Neutral',
            'is_swarm' => false,
            'size' => 'Medium',
            'creature_type' => 'Humanoid',
            'subtype' => null,
            'alignment' => 'Neutral',
            'ac' => 13,
            'initiative_bonus' => 2,
            'hp' => 22,
            'hit_dice' => '4d8 + 4',
            'speed' => '30 ft.',
            'ability_scores' => [
                'str' => ['score' => 12, 'mod' => '+1', 'save' => '+1'],
                'dex' => ['score' => 14, 'mod' => '+2', 'save' => '+2'],
                'con' => ['score' => 12, 'mod' => '+1', 'save' => '+1'],
                'int' => ['score' => 10, 'mod' => '+0', 'save' => '+0'],
                'wis' => ['score' => 11, 'mod' => '+0', 'save' => '+0'],
                'cha' => ['score' => 10, 'mod' => '+0', 'save' => '+0'],
            ],
            'skills' => 'Perception +2',
            'senses' => 'Passive Perception 12',
            'languages' => 'Common',
            'gear' => null,
            'resistances' => null,
            'immunities' => null,
            'vulnerabilities' => null,
            'cr' => '1',
            'cr_value' => 1,
            'xp' => 200,
            'cr_note' => 'PB +2',
            'traits' => null,
            'actions' => [
                ['name' => 'Shortsword', 'text' => '_Melee Attack Roll:_ +4, reach 5 ft. _Hit:_ 5 (1d6 + 2) Piercing damage.'],
            ],
            'bonus_actions' => null,
            'reactions' => null,
            'legendary_actions' => null,
        ];
    }

    /**
     * A stat block a GM can drop into a fight and read the numbers of.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function named(string $name, array $attributes = []): static
    {
        return $this->state(fn (): array => [
            'name' => $name,
            'slug' => str($name)->slug()->value(),
        ] + $attributes);
    }
}
