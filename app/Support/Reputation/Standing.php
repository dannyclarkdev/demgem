<?php

namespace App\Support\Reputation;

/**
 * A sum of reputation changes, read as a word. The bands live in
 * config/reputation.php and are demgem's own.
 */
final class Standing
{
    public function __construct(public readonly int $sum) {}

    public function label(): string
    {
        return $this->band()['label'];
    }

    public function badgeVariant(): string
    {
        return $this->band()['variant'];
    }

    /**
     * "+2" or "−3", the way a table says it out loud. Zero prints as "0".
     */
    public function signed(): string
    {
        if ($this->sum === 0) {
            return '0';
        }

        return ($this->sum < 0 ? '−' : '+').abs($this->sum);
    }

    public static function maxDelta(): int
    {
        return max(1, (int) config('reputation.max_delta', 5));
    }

    /**
     * @return array{min: int, label: string, variant: string}
     */
    private function band(): array
    {
        /** @var list<array{min: int, label: string, variant: string}> $bands */
        $bands = config('reputation.bands', []);

        foreach ($bands as $band) {
            if ($this->sum >= $band['min']) {
                return $band;
            }
        }

        return ['min' => PHP_INT_MIN, 'label' => 'Unknown', 'variant' => 'neutral'];
    }
}
