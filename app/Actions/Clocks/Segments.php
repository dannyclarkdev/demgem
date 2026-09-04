<?php

namespace App\Actions\Clocks;

use App\Models\Clock;

/**
 * A clock's size and its fill both arrive from a browser, so both arrive as a claim
 * rather than a fact, and every one that reaches the database goes through here.
 *
 * This is the same answer Coordinate gives a map pin, for the same reason and at the
 * same size: a forged value can at most set a clock the sender can already edit to a
 * number inside its own range.
 */
final class Segments
{
    public const MIN = 2;

    public const MAX = 12;

    /**
     * A dial has to be worth drawing and worth counting.
     */
    public static function clamp(int $segments): int
    {
        return max(self::MIN, min(self::MAX, $segments));
    }

    /**
     * A fill never leaves its own dial.
     */
    public static function clampFill(int $filled, int $segments): int
    {
        return max(0, min($segments, $filled));
    }
}
