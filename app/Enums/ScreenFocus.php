<?php

namespace App\Enums;

/**
 * What the television at the end of the table is showing.
 *
 * Two of these stand on their own and two name a page: a handout or a map carries
 * campaigns.screen_entity_id beside it, and the fight and the clocks carry nothing.
 * Campaign::screen() is where a focus that needs a page and has none resolves to
 * null, so the Blade never has to ask.
 */
enum ScreenFocus: string
{
    case Fight = 'fight';
    case Clocks = 'clocks';
    case Handout = 'handout';
    case Map = 'map';

    public function label(): string
    {
        return match ($this) {
            self::Fight => 'The fight',
            self::Clocks => 'The clocks',
            self::Handout => 'A handout',
            self::Map => 'A map',
        };
    }

    public function needsPage(): bool
    {
        return match ($this) {
            self::Handout, self::Map => true,
            self::Fight, self::Clocks => false,
        };
    }

    public static function forPage(EntityType $type): ?self
    {
        return match ($type) {
            EntityType::Handout => self::Handout,
            EntityType::Map => self::Map,
            default => null,
        };
    }
}
