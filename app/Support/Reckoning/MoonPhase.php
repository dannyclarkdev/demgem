<?php

namespace App\Support\Reckoning;

/**
 * The eight phases everybody names, in order from new. A cycle is cut into eight
 * equal arcs centred on each phase, so "full" covers the sixteenth of the cycle on
 * either side of the exact moment.
 */
enum MoonPhase: int
{
    case New = 0;
    case WaxingCrescent = 1;
    case FirstQuarter = 2;
    case WaxingGibbous = 3;
    case Full = 4;
    case WaningGibbous = 5;
    case LastQuarter = 6;
    case WaningCrescent = 7;

    /**
     * The phase at a point in the cycle, where 0 is new and 1 is new again.
     */
    public static function at(float $fraction): self
    {
        $fraction -= floor($fraction);

        return self::from((int) floor($fraction * 8 + 0.5) % 8);
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::WaxingCrescent => 'Waxing crescent',
            self::FirstQuarter => 'First quarter',
            self::WaxingGibbous => 'Waxing gibbous',
            self::Full => 'Full',
            self::WaningGibbous => 'Waning gibbous',
            self::LastQuarter => 'Last quarter',
            self::WaningCrescent => 'Waning crescent',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::New => '🌑',
            self::WaxingCrescent => '🌒',
            self::FirstQuarter => '🌓',
            self::WaxingGibbous => '🌔',
            self::Full => '🌕',
            self::WaningGibbous => '🌖',
            self::LastQuarter => '🌗',
            self::WaningCrescent => '🌘',
        };
    }
}
