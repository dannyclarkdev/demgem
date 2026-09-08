<?php

namespace App\Actions\Calendars;

use App\Models\Calendar;
use App\Models\Campaign;

/**
 * Writes the whole calendar at once, new or not. A campaign has one, so this is an
 * upsert on campaign_id, and every list arrives already validated and already
 * shaped: the form and the importer both hand over the arrays Reckoning takes.
 */
class SaveCalendar
{
    /**
     * @param  array{
     *     name: string,
     *     era: string|null,
     *     months: list<array{name: string, days: int}>,
     *     weekdays: list<string>,
     *     moons: list<array{name: string, cycle: float, offset: int}>,
     *     leap_every: int|null,
     *     leap_month: int|null,
     *     current_year: int,
     *     current_month: int,
     *     current_day: int,
     * }  $attributes
     */
    public function handle(Campaign $campaign, array $attributes): Calendar
    {
        return $campaign->calendar()->updateOrCreate([], $attributes);
    }
}
