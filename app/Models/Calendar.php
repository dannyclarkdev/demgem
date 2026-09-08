<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use App\Support\Reckoning\GameDate;
use App\Support\Reckoning\Reckoning;
use Database\Factories\CalendarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The world's calendar and the day it is in the world. One per campaign.
 *
 * The row is storage; the arithmetic is Reckoning, which reckoning() builds from the
 * row and which has no idea a database exists. Every screen that prints a date asks
 * the Reckoning, never the row.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $name
 * @property string|null $era
 * @property list<array{name: string, days: int}> $months
 * @property list<string> $weekdays
 * @property list<array{name: string, cycle: float, offset: int}> $moons
 * @property int|null $leap_every
 * @property int|null $leap_month
 * @property int $current_year
 * @property int $current_month
 * @property int $current_day
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 */
#[Fillable([
    'campaign_id', 'name', 'era', 'months', 'weekdays', 'moons', 'leap_every', 'leap_month',
    'current_year', 'current_month', 'current_day',
])]
class Calendar extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<CalendarFactory> */
    use HasFactory, HasUlids;

    public const MAX_NAME_LENGTH = 60;

    public const MAX_ERA_LENGTH = 12;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'months' => 'array',
            'weekdays' => 'array',
            'moons' => 'array',
            'leap_every' => 'integer',
            'leap_month' => 'integer',
            'current_year' => 'integer',
            'current_month' => 'integer',
            'current_day' => 'integer',
        ];
    }

    /**
     * JSON has no float, so a cycle of 8.0 comes back as 8; the arithmetic wants the
     * types Reckoning declares, and this is the one place the row is read into it.
     */
    public function reckoning(): Reckoning
    {
        return new Reckoning(
            months: array_map(fn (array $month): array => ['name' => (string) $month['name'], 'days' => (int) $month['days']], $this->months),
            weekdays: $this->weekdays,
            moons: array_map(fn (array $moon): array => ['name' => (string) $moon['name'], 'cycle' => (float) $moon['cycle'], 'offset' => (int) $moon['offset']], $this->moons),
            leapEvery: $this->leap_every,
            leapMonth: $this->leap_month,
            era: $this->era ?? '',
        );
    }

    public function today(): GameDate
    {
        return new GameDate($this->current_year, $this->current_month, $this->current_day);
    }

    /**
     * "Tideday, 3 Harvestmoon 1042 AR", the line the dashboard leads with.
     */
    public function todayFormatted(): string
    {
        return $this->reckoning()->format($this->today());
    }
}
