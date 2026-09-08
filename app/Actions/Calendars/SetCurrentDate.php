<?php

namespace App\Actions\Calendars;

use App\Models\Calendar;
use App\Support\Reckoning\GameDate;
use InvalidArgumentException;

/**
 * Sets the world's day outright. The date has to exist in this calendar; the form
 * checks that first, and this throws rather than writing a day the month lacks.
 */
class SetCurrentDate
{
    public function handle(Calendar $calendar, GameDate $date): Calendar
    {
        if (! $calendar->reckoning()->isValid($date)) {
            throw new InvalidArgumentException('That date is not in this calendar.');
        }

        $calendar->update([
            'current_year' => $date->year,
            'current_month' => $date->month,
            'current_day' => $date->day,
        ]);

        return $calendar;
    }
}
