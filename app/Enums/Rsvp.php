<?php

namespace App\Enums;

enum Rsvp: string
{
    case Yes = 'yes';
    case No = 'no';
    case Maybe = 'maybe';

    public function label(): string
    {
        return match ($this) {
            self::Yes => 'Yes',
            self::No => 'No',
            self::Maybe => 'Maybe',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Yes => 'success',
            self::No => 'danger',
            self::Maybe => 'accent',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Yes => 'check',
            self::No => 'x',
            self::Maybe => 'info',
        };
    }
}
