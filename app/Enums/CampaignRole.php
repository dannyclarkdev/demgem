<?php

namespace App\Enums;

enum CampaignRole: string
{
    case Owner = 'owner';
    case CoGm = 'co_gm';
    case Player = 'player';
    case Spectator = 'spectator';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::CoGm => 'Co-GM',
            self::Player => 'Player',
            self::Spectator => 'Spectator',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Runs the campaign. Can delete it and manage every member.',
            self::CoGm => 'Sees everything the owner sees. Can invite and remove players.',
            self::Player => 'Sees what the GM reveals. Can edit their own character.',
            self::Spectator => 'Read-only access to what players can see.',
        };
    }

    public function isDm(): bool
    {
        return $this === self::Owner || $this === self::CoGm;
    }

    /**
     * Roles an invite link may grant.
     *
     * @return list<self>
     */
    public static function invitable(): array
    {
        return [self::CoGm, self::Player, self::Spectator];
    }

    /**
     * Sort weight for member lists. Lower comes first.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Owner => 0,
            self::CoGm => 1,
            self::Player => 2,
            self::Spectator => 3,
        };
    }
}
