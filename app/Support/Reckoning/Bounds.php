<?php

namespace App\Support\Reckoning;

/**
 * The limits on a calendar's shape. They arrive from a browser or from an uploaded
 * file, so every one that reaches the database goes through here, the way a clock's
 * segments go through Segments and a pin through Coordinate.
 *
 * The numbers are generous rather than tight: a world with twenty-four months or a
 * nine-hundred-day month is odd, and odd is allowed. What is refused is what breaks
 * the arithmetic or the grid: a month with no days, a week with no end, a year
 * before the first.
 */
final class Bounds
{
    public const MIN_MONTHS = 1;

    public const MAX_MONTHS = 24;

    public const MIN_DAYS = 1;

    public const MAX_DAYS = 999;

    public const MAX_WEEKDAYS = 14;

    public const MAX_MOONS = 6;

    public const MIN_CYCLE = 1.0;

    public const MAX_CYCLE = 999.0;

    public const MIN_LEAP_EVERY = 2;

    public const MAX_LEAP_EVERY = 1000;

    public const MIN_YEAR = 1;

    public const MAX_YEAR = 99999;

    public const MAX_NAME_LENGTH = 30;

    public static function clampDays(int $days): int
    {
        return max(self::MIN_DAYS, min(self::MAX_DAYS, $days));
    }

    public static function clampCycle(float $cycle): float
    {
        if (is_nan($cycle)) {
            return self::MIN_CYCLE;
        }

        return round(max(self::MIN_CYCLE, min(self::MAX_CYCLE, $cycle)), 2);
    }

    /**
     * An offset is a point in the cycle, so it never leaves it.
     */
    public static function clampOffset(int $offset, float $cycle): int
    {
        return max(0, min((int) ceil($cycle) - 1, $offset));
    }

    public static function clampYear(int $year): int
    {
        return max(self::MIN_YEAR, min(self::MAX_YEAR, $year));
    }
}
