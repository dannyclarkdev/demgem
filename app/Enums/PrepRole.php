<?php

namespace App\Enums;

/**
 * The four buckets a GM fills while prepping a session. A bucket is not an entity type:
 * a monster can be a character or a note, and the GM decides what belongs where.
 */
enum PrepRole: string
{
    case Npc = 'npc';
    case Location = 'location';
    case Monster = 'monster';
    case Treasure = 'treasure';

    public function label(): string
    {
        return match ($this) {
            self::Npc => 'NPC',
            self::Location => 'Location',
            self::Monster => 'Monster',
            self::Treasure => 'Treasure',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Npc => 'NPCs',
            self::Location => 'Locations',
            self::Monster => 'Monsters',
            self::Treasure => 'Treasure',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Npc => 'Who the party can talk to tonight.',
            self::Location => 'Where tonight happens.',
            self::Monster => 'What might fight them.',
            self::Treasure => 'What they can walk away with.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Npc => 'user',
            self::Location => 'map-pin',
            self::Monster => 'shield',
            self::Treasure => 'box',
        };
    }

    /**
     * Sorts the entity picker. It never limits what the GM may attach.
     *
     * @return list<EntityType>
     */
    public function suggestedTypes(): array
    {
        return match ($this) {
            self::Npc => [EntityType::Character, EntityType::Faction],
            self::Location => [EntityType::Location],
            self::Monster => [EntityType::Character, EntityType::Note],
            self::Treasure => [EntityType::Item],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
