<?php

namespace App\Actions\Maps;

/**
 * A map coordinate is a percentage of the image, and it comes from a browser.
 *
 * A browser sends whatever it likes, so every coordinate that reaches the database
 * goes through here first. Three decimals is a sixteenth of a pixel on a 6000px map,
 * which is more precision than a fingertip has.
 */
final class Coordinate
{
    public const MIN = 0.0;

    public const MAX = 100.0;

    public const PRECISION = 3;

    public static function clamp(float $value): float
    {
        if (is_nan($value)) {
            return self::MIN;
        }

        return round(max(self::MIN, min(self::MAX, $value)), self::PRECISION);
    }
}
