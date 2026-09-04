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
    case Handout = 'handout';
    case Map = 'map';

    public function label(): string
    {
        return match ($this) {
            self::Character => 'Character',
            self::Location => 'Location',
            self::Faction => 'Faction',
            self::Item => 'Item',
            self::Quest => 'Quest',
            self::Note => 'Note',
            self::Handout => 'Handout',
            self::Map => 'Map',
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
            self::Handout => 'Handouts',
            self::Map => 'Maps',
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
            self::Handout => 'handouts',
            self::Map => 'maps',
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
            self::Handout => 'paperclip',
            self::Map => 'map',
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
            self::Handout => 'The letter, the map fragment, the ledger page. Things the party holds, with the files attached.',
            self::Map => 'The picture of the world, with a pin on everything the party has found.',
        };
    }

    /**
     * Resolution order for a bare [[Name]] that matches more than one type. Lower wins.
     *
     * A map sits last on purpose. "The Salt Cathedral" in prose means the place, and
     * a map of the same name is the picture of it; the reader wants the place. A
     * handout sits second to last for the same reason: the duke's letter is a thing
     * about the duke, and prose that says his name means him.
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
            self::Handout => 6,
            self::Map => 7,
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
