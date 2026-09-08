<?php

namespace App\Support\Reckoning;

/**
 * A day in the world: a year, a month, and a day of the month, each counted from one.
 *
 * It is three integers rather than a day number on purpose. A day number only means
 * something under one calendar, and the GM edits the calendar; "3 Harvestmoon 1042"
 * is still the third day of the third month after the GM renames a month or gives
 * another one a day. Sorting works on the triple without a calendar at all.
 */
final readonly class GameDate
{
    public function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {}

    /**
     * @param  array{year: int, month: int, day: int}  $values
     */
    public static function fromArray(array $values): self
    {
        return new self($values['year'], $values['month'], $values['day']);
    }

    /**
     * @return array{year: int, month: int, day: int}
     */
    public function toArray(): array
    {
        return ['year' => $this->year, 'month' => $this->month, 'day' => $this->day];
    }

    /**
     * -1, 0, or 1, the way a sort callback wants it.
     */
    public function compare(self $other): int
    {
        return [$this->year, $this->month, $this->day] <=> [$other->year, $other->month, $other->day];
    }

    public function equals(self $other): bool
    {
        return $this->compare($other) === 0;
    }

    public function isBefore(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function isAfter(self $other): bool
    {
        return $this->compare($other) > 0;
    }
}
