<?php

namespace App\Casts;

use App\Support\Reckoning\GameDate;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use InvalidArgumentException;

/**
 * Three integer columns read and written as one GameDate.
 *
 * Declared as `'in_game_start' => GameDateCast::class.':in_game_start'`: the argument
 * is the column prefix, and the columns are {prefix}_year, {prefix}_month and
 * {prefix}_day. The three are nullable as a group, so a date is either whole or
 * absent, and the model never hands out a year with no day.
 *
 * @implements CastsAttributes<GameDate|null, mixed>
 */
class GameDateCast implements CastsAttributes
{
    public function __construct(private readonly string $prefix) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get($model, string $key, $value, array $attributes): ?GameDate
    {
        $year = $attributes[$this->prefix.'_year'] ?? null;
        $month = $attributes[$this->prefix.'_month'] ?? null;
        $day = $attributes[$this->prefix.'_day'] ?? null;

        if ($year === null || $month === null || $day === null) {
            return null;
        }

        return new GameDate((int) $year, (int) $month, (int) $day);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|null>
     */
    public function set($model, string $key, $value, array $attributes): array
    {
        if ($value !== null && ! $value instanceof GameDate) {
            throw new InvalidArgumentException("{$key} takes a GameDate or null.");
        }

        return [
            $this->prefix.'_year' => $value?->year,
            $this->prefix.'_month' => $value?->month,
            $this->prefix.'_day' => $value?->day,
        ];
    }
}
