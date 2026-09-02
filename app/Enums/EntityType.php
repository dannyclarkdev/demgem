<?php

namespace App\Enums;

enum EntityType: string
{
    case Character = 'character';
    case Location = 'location';
    case Faction = 'faction';
    case Item = 'item';
    case Quest = 'quest';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Character => 'Character',
            self::Location => 'Location',
            self::Faction => 'Faction',
            self::Item => 'Item',
            self::Quest => 'Quest',
            self::Note => 'Note',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Character => 'Characters',
            self::Location => 'Locations',
            self::Faction => 'Factions',
            self::Item => 'Items',
            self::Quest => 'Quests',
            self::Note => 'Notes',
        };
    }

    /**
     * URL segment. Plural, lowercase.
     */
    public function slug(): string
    {
        return match ($this) {
            self::Character => 'characters',
            self::Location => 'locations',
            self::Faction => 'factions',
            self::Item => 'items',
            self::Quest => 'quests',
            self::Note => 'notes',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Character => 'user',
            self::Location => 'map-pin',
            self::Faction => 'shield',
            self::Item => 'box',
            self::Quest => 'compass',
            self::Note => 'file-text',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Character => 'Player characters, villains, shopkeepers, and everyone in between.',
            self::Location => 'Continents down to taverns. Nest them to build a map in words.',
            self::Faction => 'Guilds, cults, courts, and crews. Who wants what, and who stands in their way.',
            self::Item => 'Relics, letters, keys, and loot worth remembering.',
            self::Quest => 'Hooks, jobs, and promises the party made.',
            self::Note => 'Lore, house rules, timelines, and anything else.',
        };
    }

    /**
     * Resolution order for a bare [[Name]] that matches more than one type. Lower wins.
     */
    public function priority(): int
    {
        return match ($this) {
            self::Character => 0,
            self::Location => 1,
            self::Faction => 2,
            self::Item => 3,
            self::Quest => 4,
            self::Note => 5,
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->slug() === $slug) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_map(fn (self $case) => $case->slug(), self::cases());
    }
}
