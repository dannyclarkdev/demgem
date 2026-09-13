<?php

namespace App\Actions\Characters;

use App\Models\CharacterSheet;

/**
 * The two things a table does to a sheet every hour. Damage comes off temporary
 * hit points first and the current ones after, never below zero; healing adds to
 * the current ones, never above the maximum. Neither touches the maximum.
 */
class AdjustHitPoints
{
    public function damage(CharacterSheet $sheet, int $amount): CharacterSheet
    {
        $amount = max(0, $amount);
        $fromTemp = min($sheet->hp_temp, $amount);

        $sheet->fill([
            'hp_temp' => $sheet->hp_temp - $fromTemp,
            'hp_current' => max(0, $sheet->hp_current - ($amount - $fromTemp)),
        ])->save();

        return $sheet;
    }

    public function heal(CharacterSheet $sheet, int $amount): CharacterSheet
    {
        $sheet->fill([
            'hp_current' => min($sheet->hp_max, $sheet->hp_current + max(0, $amount)),
        ])->save();

        return $sheet;
    }
}
