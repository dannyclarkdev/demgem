<?php

use App\Support\Reckoning\GameDate;
use App\Support\Reckoning\MoonPhase;
use App\Support\Reckoning\Reckoning;

/**
 * Three months of 10, 20, and 30 days, a two-day week, one eight-day moon, and a leap
 * day on the first month every fourth year. Small enough to count by hand.
 */
function tinyReckoning(): Reckoning
{
    return new Reckoning(
        months: [
            ['name' => 'Thaw', 'days' => 10],
            ['name' => 'Harvest', 'days' => 20],
            ['name' => 'Frost', 'days' => 30],
        ],
        weekdays: ['Sunday', 'Moonday'],
        moons: [['name' => 'Pale', 'cycle' => 8.0, 'offset' => 0]],
        leapEvery: 4,
        leapMonth: 1,
        era: 'AR',
    );
}

it('counts the first day of the first year as day zero', function () {
    expect(tinyReckoning()->dayNumber(new GameDate(1, 1, 1)))->toBe(0);
});

it('counts days across month ends', function () {
    $reckoning = tinyReckoning();

    expect($reckoning->dayNumber(new GameDate(1, 1, 10)))->toBe(9)
        ->and($reckoning->dayNumber(new GameDate(1, 2, 1)))->toBe(10)
        ->and($reckoning->dayNumber(new GameDate(1, 3, 1)))->toBe(30)
        ->and($reckoning->dayNumber(new GameDate(2, 1, 1)))->toBe(60);
});

it('adds one day to the leap month every fourth year', function () {
    $reckoning = tinyReckoning();

    expect($reckoning->isLeapYear(4))->toBeTrue()
        ->and($reckoning->isLeapYear(5))->toBeFalse()
        ->and($reckoning->daysInMonth(1, 4))->toBe(11)
        ->and($reckoning->daysInMonth(1, 5))->toBe(10)
        ->and($reckoning->daysInYear(4))->toBe(61)
        ->and($reckoning->dayNumber(new GameDate(5, 1, 1)))->toBe(60 * 4 + 1);
});

it('inverts the day number for a thousand days', function () {
    $reckoning = tinyReckoning();

    for ($day = 0; $day < 1000; $day++) {
        $date = $reckoning->dateAt($day);

        expect($reckoning->isValid($date))->toBeTrue()
            ->and($reckoning->dayNumber($date))->toBe($day);
    }
});

it('adds days across a year end', function () {
    $date = tinyReckoning()->add(new GameDate(1, 3, 30), 1);

    expect($date->year)->toBe(2)
        ->and($date->month)->toBe(1)
        ->and($date->day)->toBe(1);
});

it('cycles the weekday through a leap day', function () {
    $reckoning = tinyReckoning();

    expect($reckoning->weekdayOf(new GameDate(1, 1, 1)))->toBe('Sunday')
        ->and($reckoning->weekdayOf(new GameDate(1, 1, 2)))->toBe('Moonday')
        // Year 4 has 61 days, so year 5 starts one weekday later than year 4 did.
        ->and($reckoning->weekdayOf(new GameDate(4, 1, 1)))->toBe('Sunday')
        ->and($reckoning->weekdayOf(new GameDate(5, 1, 1)))->toBe('Moonday');
});

it('has no weekday when the calendar has no week', function () {
    $reckoning = new Reckoning(months: [['name' => 'Only', 'days' => 5]], weekdays: [], moons: []);

    expect($reckoning->weekdayOf(new GameDate(1, 1, 3)))->toBeNull();
});

it('reads the moon from new to full and back', function () {
    $reckoning = tinyReckoning();

    expect($reckoning->phases(new GameDate(1, 1, 1))[0]->phase)->toBe(MoonPhase::New)
        ->and($reckoning->phases(new GameDate(1, 1, 3))[0]->phase)->toBe(MoonPhase::FirstQuarter)
        ->and($reckoning->phases(new GameDate(1, 1, 5))[0]->phase)->toBe(MoonPhase::Full)
        ->and($reckoning->phases(new GameDate(1, 1, 7))[0]->phase)->toBe(MoonPhase::LastQuarter)
        ->and($reckoning->phases(new GameDate(1, 1, 9))[0]->phase)->toBe(MoonPhase::New)
        ->and($reckoning->phases(new GameDate(1, 1, 1))[0]->name)->toBe('Pale');
});

it('shifts the moon by its offset', function () {
    $reckoning = new Reckoning(
        months: [['name' => 'Only', 'days' => 30]],
        weekdays: [],
        moons: [['name' => 'Late', 'cycle' => 8.0, 'offset' => 4]],
    );

    expect($reckoning->phases(new GameDate(1, 1, 1))[0]->phase)->toBe(MoonPhase::Full);
});

it('formats a date with the weekday and the era', function () {
    expect(tinyReckoning()->format(new GameDate(1042, 2, 3)))->toBe('Sunday, 3 Harvest 1042 AR');
});

it('formats a date without a week or an era', function () {
    $reckoning = new Reckoning(months: [['name' => 'Only', 'days' => 30]], weekdays: [], moons: []);

    expect($reckoning->format(new GameDate(7, 1, 12)))->toBe('12 Only 7');
});

it('formats a month the calendar no longer has', function () {
    expect(tinyReckoning()->format(new GameDate(3, 9, 2)))->toBe('2 month 9, 3 AR')
        ->and(tinyReckoning()->isValid(new GameDate(3, 9, 2)))->toBeFalse();
});

it('refuses a day the month does not have, and a year before one', function () {
    $reckoning = tinyReckoning();

    expect($reckoning->isValid(new GameDate(1, 1, 11)))->toBeFalse()
        ->and($reckoning->isValid(new GameDate(4, 1, 11)))->toBeTrue()
        ->and($reckoning->isValid(new GameDate(0, 1, 1)))->toBeFalse()
        ->and($reckoning->isValid(new GameDate(1, 1, 0)))->toBeFalse();
});

it('compares dates as a triple', function () {
    expect((new GameDate(2, 1, 1))->compare(new GameDate(1, 3, 30)))->toBe(1)
        ->and((new GameDate(1, 2, 5))->compare(new GameDate(1, 2, 5)))->toBe(0)
        ->and((new GameDate(1, 2, 5))->compare(new GameDate(1, 2, 6)))->toBe(-1)
        ->and((new GameDate(1, 2, 5))->equals(GameDate::fromArray(['year' => 1, 'month' => 2, 'day' => 5])))->toBeTrue();
});

it('gives the earth a leap day in February every fourth year', function () {
    $earth = Reckoning::earth();

    expect($earth->monthCount())->toBe(12)
        ->and($earth->daysInYear(1))->toBe(365)
        ->and($earth->daysInYear(4))->toBe(366)
        ->and($earth->daysInMonth(2, 4))->toBe(29)
        ->and($earth->weekdayOf(new GameDate(1, 1, 1)))->toBe('Monday')
        ->and($earth->format(new GameDate(1, 1, 1)))->toBe('Monday, 1 January 1');
});
