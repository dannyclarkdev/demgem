<?php

namespace App\Enums;

enum QuestStatus: string
{
    case Available = 'available';
    case Active = 'active';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Available => 'On offer. The party has heard of it, or is about to.',
            self::Active => 'The party took it. It belongs on the Run screen.',
            self::Completed => 'Done, whatever it cost.',
            self::Failed => 'Over, and not the way anyone wanted.',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Available => 'neutral',
            self::Active => 'accent',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Available => 'flag',
            self::Active => 'target',
            self::Completed => 'check',
            self::Failed => 'x',
        };
    }

    /**
     * Still in play. Available and active quests are the ones a GM and a party care about.
     */
    public function isOpen(): bool
    {
        return $this === self::Available || $this === self::Active;
    }
}
