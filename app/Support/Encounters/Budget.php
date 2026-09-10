<?php

namespace App\Support\Encounters;

use App\Enums\EncounterDifficulty;

/**
 * What a party can afford, and what the fight in front of them costs.
 *
 * A plain class with no database in it, the way Reckoning holds the calendar's
 * arithmetic: the component gathers the levels and the prices, this decides the
 * answer, and a test can put numbers in without building a campaign.
 *
 * The numbers are demgem's own and config/encounters.php says why. Nothing here
 * claims a published book agrees.
 */
final class Budget
{
    public const MIN_LEVEL = 1;

    public const MAX_LEVEL = 20;

    private function __construct(
        public readonly int $low,
        public readonly int $moderate,
        public readonly int $high,
        public readonly int $characters,
    ) {}

    /**
     * The budget for a party, one level per character.
     *
     * A character with no level counts as level 1. It understates them, and that is
     * the conservative direction: a budget that silently shrank when a GM forgot to
     * fill a field would be wrong with nothing on screen to explain it. The read-out
     * says how many characters it had to guess for.
     *
     * @param  list<int|null>  $levels
     */
    public static function forParty(array $levels): self
    {
        $low = $moderate = $high = 0;
        $bands = self::bands();

        foreach ($levels as $level) {
            $xp = self::xpForChallenge((float) self::clampLevel($level));

            $low += (int) round($xp * $bands['low']);
            $moderate += (int) round($xp * $bands['moderate']);
            $high += (int) round($xp * $bands['high']);
        }

        return new self($low, $moderate, $high, count($levels));
    }

    /**
     * Which band a fight lands in. Trivial under the first threshold, Deadly over the
     * last, because "High" printed for a fight worth four times High tells a GM
     * nothing they can act on.
     *
     * Meaningless with no characters, and hasParty() is what the screen asks first.
     */
    public function difficultyFor(int $spent): EncounterDifficulty
    {
        return match (true) {
            $spent >= $this->high => $spent >= $this->high * 2
                ? EncounterDifficulty::Deadly
                : EncounterDifficulty::High,
            $spent >= $this->moderate => EncounterDifficulty::Moderate,
            $spent >= $this->low => EncounterDifficulty::Low,
            default => EncounterDifficulty::Trivial,
        };
    }

    public function hasParty(): bool
    {
        return $this->characters > 0;
    }

    /**
     * What a creature of this challenge rating is worth.
     *
     * The ladder is read out of the shipped dataset and has gaps, because the SRD
     * names no creature at CR 18 or between 25 and 29. A rating between two rungs is
     * read in a straight line between them; one past either end takes that end.
     */
    public static function xpForChallenge(float $cr): int
    {
        $ladder = self::ladder();

        $lower = null;

        foreach ($ladder as [$rung, $xp]) {
            if (abs($rung - $cr) < 0.0001) {
                return $xp;
            }

            if ($rung < $cr) {
                $lower = [$rung, $xp];

                continue;
            }

            if ($lower === null) {
                return $xp;
            }

            [$lowerCr, $lowerXp] = $lower;

            return (int) round($lowerXp + ($xp - $lowerXp) * (($cr - $lowerCr) / ($rung - $lowerCr)));
        }

        return $lower === null ? 0 : $lower[1];
    }

    private static function clampLevel(?int $level): int
    {
        if ($level === null) {
            return self::MIN_LEVEL;
        }

        return max(self::MIN_LEVEL, min(self::MAX_LEVEL, $level));
    }

    /**
     * @return array{low: float, moderate: float, high: float}
     */
    private static function bands(): array
    {
        /** @var array{low: float, moderate: float, high: float} $bands */
        $bands = config('encounters.bands');

        return $bands;
    }

    /**
     * Challenge rating to XP, sorted by rating.
     *
     * A list of pairs rather than a keyed array: the config keys the table by rating
     * as a string because a PHP array key cannot be a float, and PHP would then cast
     * "1" back to an integer key while leaving "0.25" a string. Pairs keep every rung
     * the same type.
     *
     * @return list<array{float, int}>
     */
    private static function ladder(): array
    {
        /** @var array<string, int> $configured */
        $configured = config('encounters.ladder');

        $ladder = [];

        foreach ($configured as $cr => $xp) {
            $ladder[] = [(float) $cr, $xp];
        }

        usort($ladder, fn (array $a, array $b) => $a[0] <=> $b[0]);

        return $ladder;
    }
}
