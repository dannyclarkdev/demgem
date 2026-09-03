<?php

namespace App\Support\Dice;

enum KeepMode: string
{
    case Highest = 'kh';
    case Lowest = 'kl';

    public function label(): string
    {
        return match ($this) {
            self::Highest => 'keep highest',
            self::Lowest => 'keep lowest',
        };
    }
}
