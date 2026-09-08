<?php

namespace App\Livewire\Calendars;

use App\Actions\Calendars\SaveCalendar;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Calendar;
use App\Models\Campaign;
use App\Support\Reckoning\Bounds;
use App\Support\Reckoning\GameDate;
use App\Support\Reckoning\Reckoning;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The shape of the world's calendar, edited whole. GM only; a player gets a 403.
 *
 * A new calendar starts as the Earth's, because a GM edits a calendar far more
 * readily than they invent one from a blank form, and half of them only want to
 * rename the months.
 */
#[Title('Calendar')]
class Edit extends Component
{
    use InteractsWithCampaign;

    public string $name = '';

    public string $era = '';

    /** @var list<array{name: string, days: int|string}> */
    public array $months = [];

    /** One field, comma separated. A week is a short list and a row per day is a wall of inputs. */
    public string $weekdays = '';

    /** @var list<array{name: string, cycle: float|string, offset: int|string}> */
    public array $moons = [];

    /** Strings, because a select and a number input both carry '' for off. */
    public string $leapEvery = '';

    public string $leapMonth = '';

    /** @var array{year: int|string, month: int|string, day: int|string} */
    public array $current = ['year' => 1, 'month' => 1, 'day' => 1];

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('update', $campaign);

        $calendar = Calendar::query()->first();

        if ($calendar === null) {
            $this->name = 'The calendar';
            $this->fillShape(Reckoning::earth());

            return;
        }

        $this->name = $calendar->name;
        $this->era = $calendar->era ?? '';
        $this->fillShape($calendar->reckoning());
        $this->current = $calendar->today()->toArray();
    }

    public function addMonth(): void
    {
        if (count($this->months) < Bounds::MAX_MONTHS) {
            $this->months[] = ['name' => '', 'days' => 30];
        }
    }

    public function removeMonth(int $index): void
    {
        unset($this->months[$index]);

        $this->months = array_values($this->months);
    }

    public function addMoon(): void
    {
        if (count($this->moons) < Bounds::MAX_MOONS) {
            $this->moons[] = ['name' => '', 'cycle' => 29.53, 'offset' => 0];
        }
    }

    public function removeMoon(int $index): void
    {
        unset($this->moons[$index]);

        $this->moons = array_values($this->moons);
    }

    public function useEarth(): void
    {
        $this->fillShape(Reckoning::earth());
        $this->resetErrorBag();
    }

    public function save(SaveCalendar $saveCalendar): void
    {
        $this->authorize('update', $this->campaign);

        $validator = Validator::make($this->all(), [
            'name' => ['required', 'string', 'max:'.Calendar::MAX_NAME_LENGTH],
            'era' => ['nullable', 'string', 'max:'.Calendar::MAX_ERA_LENGTH],
            'months' => ['required', 'array', 'min:'.Bounds::MIN_MONTHS, 'max:'.Bounds::MAX_MONTHS],
            'months.*.name' => ['required', 'string', 'max:'.Bounds::MAX_NAME_LENGTH],
            'months.*.days' => ['required', 'integer', 'min:'.Bounds::MIN_DAYS, 'max:'.Bounds::MAX_DAYS],
            'weekdays' => ['nullable', 'string', 'max:500'],
            'moons' => ['array', 'max:'.Bounds::MAX_MOONS],
            'moons.*.name' => ['required', 'string', 'max:'.Bounds::MAX_NAME_LENGTH],
            'moons.*.cycle' => ['required', 'numeric', 'min:'.Bounds::MIN_CYCLE, 'max:'.Bounds::MAX_CYCLE],
            'moons.*.offset' => ['required', 'integer', 'min:0', 'max:'.Bounds::MAX_DAYS],
            'leapEvery' => ['nullable', 'integer', 'min:'.Bounds::MIN_LEAP_EVERY, 'max:'.Bounds::MAX_LEAP_EVERY],
            'leapMonth' => ['nullable', 'integer', 'min:1', 'max:'.Bounds::MAX_MONTHS],
            'current.year' => ['required', 'integer', 'min:'.Bounds::MIN_YEAR, 'max:'.Bounds::MAX_YEAR],
            'current.month' => ['required', 'integer', 'min:1', 'max:'.Bounds::MAX_MONTHS],
            'current.day' => ['required', 'integer', 'min:1', 'max:'.Bounds::MAX_DAYS],
        ], [
            'months.*.name.required' => 'Every month needs a name.',
            'months.*.days.min' => 'A month needs at least one day.',
            'moons.*.name.required' => 'Every moon needs a name.',
        ]);

        $validator->after(fn (ValidatorInstance $validator) => $this->checkShape($validator));

        $validated = $validator->validate();

        $attributes = $this->attributesFrom($validated);

        $saveCalendar->handle($this->campaign, $attributes);

        session()->flash('status', 'Calendar saved.');

        $this->redirectRoute('calendar.show', $this->campaign);
    }

    public function render(): View
    {
        return view('livewire.calendars.edit', [
            'maxMonths' => Bounds::MAX_MONTHS,
            'maxMoons' => Bounds::MAX_MOONS,
        ]);
    }

    /**
     * The checks a rule list cannot say: the leap month and the current date have to
     * exist in the calendar being saved, and the week has to fit.
     */
    private function checkShape(ValidatorInstance $validator): void
    {
        $reckoning = $this->reckoningFrom();

        if ($this->leapMonth !== '' && ! $reckoning->hasMonth((int) $this->leapMonth)) {
            $validator->errors()->add('leapMonth', 'The leap month has to be one of the months above.');
        }

        if ($this->leapEvery !== '' && $this->leapMonth === '') {
            $validator->errors()->add('leapMonth', 'Say which month gains the day.');
        }

        if (count($this->weekdayList()) > Bounds::MAX_WEEKDAYS) {
            $validator->errors()->add('weekdays', 'A week can have up to '.Bounds::MAX_WEEKDAYS.' days.');
        }

        foreach ($this->weekdayList() as $weekday) {
            if (mb_strlen($weekday) > Bounds::MAX_NAME_LENGTH) {
                $validator->errors()->add('weekdays', 'A weekday name can be up to '.Bounds::MAX_NAME_LENGTH.' characters.');
                break;
            }
        }

        if ($validator->errors()->hasAny(['months', 'months.*', 'current.year', 'current.month', 'current.day'])) {
            return;
        }

        $date = new GameDate((int) $this->current['year'], (int) $this->current['month'], (int) $this->current['day']);

        if (! $reckoning->hasMonth($date->month)) {
            $validator->errors()->add('current.month', 'That month is not in this calendar.');
        } elseif (! $reckoning->isValid($date)) {
            $validator->errors()->add('current.day', $reckoning->monthName($date->month).' has '.$reckoning->daysInMonth($date->month, $date->year).' days that year.');
        }
    }

    private function reckoningFrom(): Reckoning
    {
        $months = [];

        foreach ($this->months as $month) {
            $months[] = ['name' => trim((string) $month['name']), 'days' => (int) $month['days']];
        }

        $moons = [];

        foreach ($this->moons as $moon) {
            $cycle = Bounds::clampCycle((float) $moon['cycle']);
            $moons[] = [
                'name' => trim((string) $moon['name']),
                'cycle' => $cycle,
                'offset' => Bounds::clampOffset((int) $moon['offset'], $cycle),
            ];
        }

        $leap = $this->leapEvery !== '' && $this->leapMonth !== '';

        return new Reckoning(
            months: $months,
            weekdays: $this->weekdayList(),
            moons: $moons,
            leapEvery: $leap ? (int) $this->leapEvery : null,
            leapMonth: $leap ? (int) $this->leapMonth : null,
            era: trim($this->era),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, era: string|null, months: list<array{name: string, days: int}>, weekdays: list<string>, moons: list<array{name: string, cycle: float, offset: int}>, leap_every: int|null, leap_month: int|null, current_year: int, current_month: int, current_day: int}
     */
    private function attributesFrom(array $validated): array
    {
        $reckoning = $this->reckoningFrom();

        return [
            'name' => trim((string) $validated['name']),
            'era' => $reckoning->era !== '' ? $reckoning->era : null,
            'months' => $reckoning->months,
            'weekdays' => $reckoning->weekdays,
            'moons' => $reckoning->moons,
            'leap_every' => $reckoning->leapEvery,
            'leap_month' => $reckoning->leapMonth,
            'current_year' => (int) $this->current['year'],
            'current_month' => (int) $this->current['month'],
            'current_day' => (int) $this->current['day'],
        ];
    }

    /**
     * @return list<string>
     */
    private function weekdayList(): array
    {
        $names = [];

        foreach (explode(',', $this->weekdays) as $name) {
            if (trim($name) !== '') {
                $names[] = trim($name);
            }
        }

        return $names;
    }

    private function fillShape(Reckoning $reckoning): void
    {
        $this->months = $reckoning->months;
        $this->weekdays = implode(', ', $reckoning->weekdays);
        $this->moons = $reckoning->moons;
        $this->leapEvery = (string) ($reckoning->leapEvery ?? '');
        $this->leapMonth = (string) ($reckoning->leapMonth ?? '');
    }
}
