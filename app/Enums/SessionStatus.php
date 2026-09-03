<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Planned = 'planned';
    case Played = 'played';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Played => 'Played',
            self::Cancelled => 'Cancelled',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Planned => 'On the calendar, or waiting for a date.',
            self::Played => 'The group played it. Write the recap.',
            self::Cancelled => 'Called off. The party still sees it, so nobody turns up.',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Planned => 'accent',
            self::Played => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Planned => 'calendar',
            self::Played => 'check',
            self::Cancelled => 'x',
        };
    }
}
