<?php

namespace Database\Factories;

use App\Models\Calendar;
use App\Models\Campaign;
use App\Support\Reckoning\GameDate;
use App\Support\Reckoning\Reckoning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Calendar>
 */
class CalendarFactory extends Factory
{
    /**
     * Three short months, a two-day week, one eight-day moon, and a leap day every
     * fourth year: the same tiny calendar the reckoning's unit test counts by hand.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'name' => 'The Tide Reckoning',
            'era' => 'AR',
            'months' => [
                ['name' => 'Thaw', 'days' => 10],
                ['name' => 'Harvest', 'days' => 20],
                ['name' => 'Frost', 'days' => 30],
            ],
            'weekdays' => ['Sunday', 'Moonday'],
            'moons' => [['name' => 'Pale', 'cycle' => 8.0, 'offset' => 0]],
            'leap_every' => 4,
            'leap_month' => 1,
            'current_year' => 1042,
            'current_month' => 2,
            'current_day' => 3,
        ];
    }

    public function inCampaign(Campaign $campaign): static
    {
        return $this->state(['campaign_id' => $campaign->id]);
    }

    public function on(GameDate $date): static
    {
        return $this->state([
            'current_year' => $date->year,
            'current_month' => $date->month,
            'current_day' => $date->day,
        ]);
    }

    public function earth(): static
    {
        $earth = Reckoning::earth();

        return $this->state([
            'name' => 'Gregorian',
            'era' => null,
            'months' => $earth->months,
            'weekdays' => $earth->weekdays,
            'moons' => $earth->moons,
            'leap_every' => $earth->leapEvery,
            'leap_month' => $earth->leapMonth,
        ]);
    }
}
