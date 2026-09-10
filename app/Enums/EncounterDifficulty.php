<?php

namespace App\Enums;

/**
 * How hard a fight looks against the party's own budget.
 *
 * Three thresholds make five answers. Low, Moderate and High are the bands
 * config/encounters.php defines; Trivial is everything under the first one and Deadly
 * is everything over the last, because a GM who has built something past the top of
 * the scale wants to be told so rather than to read "High" twice.
 */
enum EncounterDifficulty: string
{
    case Trivial = 'trivial';
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';
    case Deadly = 'deadly';

    public function label(): string
    {
        return match ($this) {
            self::Trivial => 'Trivial',
            self::Low => 'Low',
            self::Moderate => 'Moderate',
            self::High => 'High',
            self::Deadly => 'Deadly',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Trivial => 'The party spends resources and little else.',
            self::Low => 'A fight the party should win without much cost.',
            self::Moderate => 'A real fight. Somebody will spend something they wanted to keep.',
            self::High => 'Dangerous. A character could go down.',
            self::Deadly => 'Past the top of the scale. A character could die.',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Trivial => 'neutral',
            self::Low => 'success',
            self::Moderate => 'accent',
            self::High, self::Deadly => 'danger',
        };
    }
}
