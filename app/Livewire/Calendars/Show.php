<?php

namespace App\Livewire\Calendars;

use App\Actions\Calendars\AdvanceDate;
use App\Actions\Calendars\SetCurrentDate;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Calendar;
use App\Models\Campaign;
use App\Support\Reckoning\Bounds;
use App\Support\Reckoning\GameDate;
use App\Support\Reckoning\MoonReading;
use App\Support\Reckoning\Reckoning;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One month of the world's calendar as a grid, with today marked, the moons on every
 * day, and the events and sessions that fall on it. Every member reads it; a GM
 * moves the day from here.
 *
 * The grid is a list of cells built here, not in the Blade. The view only draws.
 */
#[Title('Calendar')]
class Show extends Component
{
    use InteractsWithCampaign;

    /** The furthest the buttons reach. A forged number still cannot leave the year bounds. */
    private const MAX_STEP = 366;

    /** The month on screen. Null until a calendar exists. */
    public ?int $year = null;

    public ?int $month = null;

    /** @var array{year: int|string, month: int|string, day: int|string} */
    public array $date = ['year' => 1, 'month' => 1, 'day' => 1];

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);

        $calendar = $this->calendar();

        if ($calendar !== null) {
            $this->year = $calendar->current_year;
            $this->month = $calendar->current_month;
            $this->date = $calendar->today()->toArray();
        }
    }

    public function previousMonth(): void
    {
        $this->shiftMonth(-1);
    }

    public function nextMonth(): void
    {
        $this->shiftMonth(1);
    }

    public function goToToday(): void
    {
        $calendar = $this->calendar();

        if ($calendar !== null) {
            $this->year = $calendar->current_year;
            $this->month = $calendar->current_month;
        }
    }

    public function advance(int $days, AdvanceDate $advanceDate): void
    {
        $this->authorize('update', $this->campaign);

        $calendar = $this->calendar();

        abort_if($calendar === null, 404);

        $advanceDate->handle($calendar, max(-self::MAX_STEP, min(self::MAX_STEP, $days)));

        $this->date = $calendar->today()->toArray();
        $this->goToToday();
    }

    public function setDate(SetCurrentDate $setCurrentDate): void
    {
        $this->authorize('update', $this->campaign);

        $calendar = $this->calendar();

        abort_if($calendar === null, 404);

        $reckoning = $calendar->reckoning();

        $validator = Validator::make(['date' => $this->date], [
            'date.year' => ['required', 'integer', 'min:'.Bounds::MIN_YEAR, 'max:'.Bounds::MAX_YEAR],
            'date.month' => ['required', 'integer', 'min:1', 'max:'.$reckoning->monthCount()],
            'date.day' => ['required', 'integer', 'min:1', 'max:'.Bounds::MAX_DAYS],
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($reckoning): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $date = $this->requestedDate();

            if (! $reckoning->isValid($date)) {
                $validator->errors()->add('date.day', $reckoning->monthName($date->month).' has '.$reckoning->daysInMonth($date->month, $date->year).' days that year.');
            }
        });

        $validator->validate();

        $setCurrentDate->handle($calendar, $this->requestedDate());

        $this->goToToday();
    }

    public function render(): View
    {
        $calendar = $this->calendar();

        if ($calendar === null) {
            return view('livewire.calendars.show', [
                'calendar' => null,
                'role' => $this->role(),
            ]);
        }

        $reckoning = $calendar->reckoning();
        $year = $this->year ?? $calendar->current_year;
        $month = $this->month ?? $calendar->current_month;
        $today = $calendar->today();
        $first = new GameDate($year, $month, 1);

        return view('livewire.calendars.show', [
            'calendar' => $calendar,
            'role' => $this->role(),
            'reckoning' => $reckoning,
            'today' => $today,
            'todayFormatted' => $reckoning->format($today),
            'moonsToday' => $reckoning->phases($today),
            'monthTitle' => $reckoning->monthName($month).' '.trim($year.' '.$reckoning->era),
            'weekdays' => $reckoning->weekdays,
            'leadingBlanks' => $reckoning->weekdayIndex($first) ?? 0,
            'days' => $this->days($reckoning, $year, $month, $today),
        ]);
    }

    /**
     * One cell per day of the month on screen.
     *
     * @return list<array{day: int, date: GameDate, isToday: bool, moons: list<MoonReading>}>
     */
    private function days(Reckoning $reckoning, int $year, int $month, GameDate $today): array
    {
        $days = [];

        for ($day = 1; $day <= $reckoning->daysInMonth($month, $year); $day++) {
            $date = new GameDate($year, $month, $day);

            $days[] = [
                'day' => $day,
                'date' => $date,
                'isToday' => $date->equals($today),
                'moons' => $reckoning->phases($date),
            ];
        }

        return $days;
    }

    private function shiftMonth(int $by): void
    {
        $calendar = $this->calendar();

        if ($calendar === null || $this->year === null || $this->month === null) {
            return;
        }

        $count = $calendar->reckoning()->monthCount();
        $index = $this->month - 1 + $by;

        $this->year = Bounds::clampYear($this->year + intdiv($index - (($index % $count) + $count) % $count, $count));
        $this->month = (($index % $count) + $count) % $count + 1;
    }

    private function requestedDate(): GameDate
    {
        return new GameDate((int) $this->date['year'], (int) $this->date['month'], (int) $this->date['day']);
    }

    private function calendar(): ?Calendar
    {
        return Calendar::query()->first();
    }
}
