<?php

namespace App\Enums;

enum Visibility: string
{
    case Dm = 'dm';
    case Players = 'players';
    case Selected = 'selected';

    public function label(): string
    {
        return match ($this) {
            self::Dm => 'GM only',
            self::Players => 'Everyone',
            self::Selected => 'Selected players',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Dm => 'Only you and co-GMs can see this.',
            self::Players => 'Every member of the campaign can see this.',
            self::Selected => 'GMs plus the players you pick.',
        };
    }
}
