<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CharacterSheet;
use App\Models\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterSheet>
 */
class CharacterSheetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'entity_id' => Entity::factory(),
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
            'saving_throws' => [],
            'skills' => [],
            'expertise' => [],
            'hp_max' => 10,
            'hp_current' => 10,
            'hp_temp' => 0,
            'hit_die' => 8,
            'hit_dice_spent' => 0,
            'spell_slots' => [],
            'spellcasting_ability' => null,
            'armor_class' => null,
            'speed' => null,
        ];
    }

    public function forCharacter(Entity $character): static
    {
        return $this->state([
            'campaign_id' => $character->campaign_id,
            'entity_id' => $character->id,
        ]);
    }
}
