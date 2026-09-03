<?php

namespace App\Enums;

enum EncounterStatus: string
{
    case Planning = 'planning';
    case Active = 'active';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'Planning',
            self::Active => 'In play',
            self::Done => 'Done',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Planning => 'Built, not started. Add the monsters now and roll later.',
            self::Active => 'Someone is taking a turn right now.',
            self::Done => 'Over. Keep it for the record or delete it.',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Planning => 'neutral',
            self::Active => 'accent',
            self::Done => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Planning => 'clock',
            self::Active => 'swords',
            self::Done => 'check',
        };
    }

    /**
     * Sort weight for the index. In play first, because that is the one you want.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Active => 0,
            self::Planning => 1,
            self::Done => 2,
        };
    }
}
