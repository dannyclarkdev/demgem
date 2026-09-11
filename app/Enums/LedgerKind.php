<?php

namespace App\Enums;

enum LedgerKind: string
{
    case Coin = 'coin';
    case Item = 'item';

    public function label(): string
    {
        return match ($this) {
            self::Coin => 'Coin',
            self::Item => 'Item',
        };
    }
}
