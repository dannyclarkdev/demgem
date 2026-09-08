<?php

namespace App\Support\Reckoning;

/**
 * The arithmetic of one world's calendar, with no database and no framework.
 *
 * Everything reduces to a day number: the count of days since the first day of
 * year 1. A weekday is that number modulo the week; a moon's phase is that number
 * modulo the cycle; "how long ago" is a subtraction. The day number is never stored,
 * because the GM edits the calendar and a stored count would go stale. It is
 * computed from a GameDate every time, which is cheap.
 *
 * The leap rule is one rule: every N years, one month gains one day. That is the
 * Earth without the century exceptions, and it is every invented world this
 * project has met.
 */
final readonly class Reckoning
{
    /**
     * @param  list<array{name: string, days: int}>  $months
     * @param  list<string>  $weekdays
     * @param  list<array{name: string, cycle: float, offset: int}>  $moons
     */
    public function __construct(
        public array $months,
        public array $weekdays,
        public array $moons,
        public ?int $leapEvery = null,
        public ?int $leapMonth = null,
        public string $era = '',
    ) {}

    /**
     * Twelve months, seven days, one moon, a leap day in February every fourth year.
     * The form's starting values, so a GM edits a calendar rather than inventing one.
     */
    public static function earth(): self
    {
        return new self(
            months: [
                ['name' => 'January', 'days' => 31],
                ['name' => 'February', 'days' => 28],
                ['name' => 'March', 'days' => 31],
                ['name' => 'April', 'days' => 30],
                ['name' => 'May', 'days' => 31],
                ['name' => 'June', 'days' => 30],
                ['name' => 'July', 'days' => 31],
                ['name' => 'August', 'days' => 31],
                ['name' => 'September', 'days' => 30],
                ['name' => 'October', 'days' => 31],
                ['name' => 'November', 'days' => 30],
                ['name' => 'December', 'days' => 31],
            ],
            weekdays: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            moons: [['name' => 'The Moon', 'cycle' => 29.53, 'offset' => 0]],
            leapEvery: 4,
            leapMonth: 2,
        );
    }

    public function monthCount(): int
    {
        return count($this->months);
    }

    public function hasMonth(int $month): bool
    {
        return $month >= 1 && $month <= $this->monthCount();
    }

    /**
     * The month's name, or "month 9" for a month the calendar no longer has. A date
     * written under an older shape of the calendar still prints rather than failing.
     */
    public function monthName(int $month): string
    {
        return $this->hasMonth($month) ? $this->months[$month - 1]['name'] : 'month '.$month;
    }

    public function isLeapYear(int $year): bool
    {
        return $this->leapEvery !== null
            && $this->leapMonth !== null
            && $this->hasMonth($this->leapMonth)
            && $year % $this->leapEvery === 0;
    }

    public function daysInMonth(int $month, int $year): int
    {
        if (! $this->hasMonth($month)) {
            return 0;
        }

        $days = $this->months[$month - 1]['days'];

        return $month === $this->leapMonth && $this->isLeapYear($year) ? $days + 1 : $days;
    }

    public function daysInYear(int $year): int
    {
        return $this->commonYearLength() + ($this->isLeapYear($year) ? 1 : 0);
    }

    public function isValid(GameDate $date): bool
    {
        return $date->year >= Bounds::MIN_YEAR
            && $this->hasMonth($date->month)
            && $date->day >= 1
            && $date->day <= $this->daysInMonth($date->month, $date->year);
    }

    /**
     * Days since 1/1/1, so that date is day 0. Undefined for an invalid date; callers
     * that read user input check isValid() first.
     */
    public function dayNumber(GameDate $date): int
    {
        $days = $this->daysBeforeYear($date->year);

        for ($month = 1; $month < $date->month; $month++) {
            $days += $this->daysInMonth($month, $date->year);
        }

        return $days + $date->day - 1;
    }

    /**
     * The inverse of dayNumber(). A negative day number is clamped to day 0, because
     * this calendar has no year before the first.
     */
    public function dateAt(int $dayNumber): GameDate
    {
        $dayNumber = max(0, $dayNumber);

        // A first guess that cannot overshoot, then walk forward year by year.
        $year = intdiv($dayNumber, $this->commonYearLength() + 1) + 1;

        while ($this->daysBeforeYear($year + 1) <= $dayNumber) {
            $year++;
        }

        $remaining = $dayNumber - $this->daysBeforeYear($year);
        $month = 1;

        while ($remaining >= $this->daysInMonth($month, $year)) {
            $remaining -= $this->daysInMonth($month, $year);
            $month++;
        }

        return new GameDate($year, $month, $remaining + 1);
    }

    public function add(GameDate $date, int $days): GameDate
    {
        return $this->dateAt($this->dayNumber($date) + $days);
    }

    public function weekLength(): int
    {
        return count($this->weekdays);
    }

    /**
     * Zero-based, or null when the calendar has no week.
     */
    public function weekdayIndex(GameDate $date): ?int
    {
        if ($this->weekLength() === 0) {
            return null;
        }

        return $this->dayNumber($date) % $this->weekLength();
    }

    public function weekdayOf(GameDate $date): ?string
    {
        $index = $this->weekdayIndex($date);

        return $index === null ? null : $this->weekdays[$index];
    }

    /**
     * Every moon on one night, in the order the GM listed them.
     *
     * @return list<MoonReading>
     */
    public function phases(GameDate $date): array
    {
        $dayNumber = $this->dayNumber($date);

        return array_map(
            fn (array $moon): MoonReading => new MoonReading(
                $moon['name'],
                MoonPhase::at(fmod($dayNumber + $moon['offset'], $moon['cycle']) / $moon['cycle']),
            ),
            $this->moons,
        );
    }

    /**
     * "Tideday, 3 Harvestmoon 1042 AR". The weekday only when the calendar has a
     * week and the date is one it can place; the era only when there is one.
     */
    public function format(GameDate $date): string
    {
        $text = $this->formatBare($date);
        $weekday = $this->isValid($date) ? $this->weekdayOf($date) : null;

        return $weekday === null ? $text : $weekday.', '.$text;
    }

    /**
     * "1 Harvest 1042 AR to 3 Harvest 1042 AR", or the single day with its weekday when
     * the range is one day or has no end. Weekdays are dropped from a range because
     * two of them in one line is noise.
     */
    public function formatRange(GameDate $start, ?GameDate $end): string
    {
        if ($end === null || $end->equals($start)) {
            return $this->format($start);
        }

        return $this->formatBare($start).' to '.$this->formatBare($end);
    }

    /**
     * The date without its weekday.
     */
    public function formatBare(GameDate $date): string
    {
        $year = trim($date->year.' '.$this->era);

        if (! $this->hasMonth($date->month)) {
            return $date->day.' '.$this->monthName($date->month).', '.$year;
        }

        return $date->day.' '.$this->monthName($date->month).' '.$year;
    }

    /**
     * @return array{months: list<array{name: string, days: int}>, weekdays: list<string>, moons: list<array{name: string, cycle: float, offset: int}>, leap_every: int|null, leap_month: int|null, era: string}
     */
    public function toArray(): array
    {
        return [
            'months' => $this->months,
            'weekdays' => $this->weekdays,
            'moons' => $this->moons,
            'leap_every' => $this->leapEvery,
            'leap_month' => $this->leapMonth,
            'era' => $this->era,
        ];
    }

    private function commonYearLength(): int
    {
        return array_sum(array_column($this->months, 'days'));
    }

    private function daysBeforeYear(int $year): int
    {
        $leapYears = $this->leapEvery === null || $this->leapMonth === null || ! $this->hasMonth($this->leapMonth)
            ? 0
            : intdiv($year - 1, $this->leapEvery);

        return ($year - 1) * $this->commonYearLength() + $leapYears;
    }
}
