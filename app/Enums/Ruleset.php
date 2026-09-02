<?php

namespace App\Enums;

enum Ruleset: string
{
    case Generic = 'generic';
    case Srd5e2024 = 'srd-5e-2024';

    public function label(): string
    {
        return match ($this) {
            self::Generic => 'System agnostic',
            self::Srd5e2024 => 'D&D 5e (2024)',
        };
    }
}
