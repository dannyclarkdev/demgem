<?php

namespace App\Actions\Calendars;

use App\Models\Calendar;

/**
 * Moves the world's day by a count, forwards or back, across month and year ends.
 * The arithmetic is Reckoning's; this only writes the answer.
 */
class AdvanceDate
{
    public function handle(Calendar $calendar, int $days): Calendar
    {
        $date = $calendar->reckoning()->add($calendar->today(), $days);

        $calendar->update([
            'current_year' => $date->year,
            'current_month' => $date->month,
            'current_day' => $date->day,
        ]);

        return $calendar;
    }
}
