<?php

namespace App\Actions\Characters;

use App\Models\CharacterSheet;
use App\Support\Sheets\FifthEdition;

/**
 * The long rest as the SRD states it: every hit point back, temporary hit points
 * gone, every spell slot back, and half the hit dice back, at least one.
 */
class LongRest
{
    public function handle(CharacterSheet $sheet): CharacterSheet
    {
        $sheet->fill([
            'hp_current' => $sheet->hp_max,
            'hp_temp' => 0,
            'hit_dice_spent' => FifthEdition::hitDiceAfterLongRest($sheet->hit_dice_spent, $sheet->level()),
            'spell_slots' => array_map(fn (array $slot) => ['total' => (int) $slot['total'], 'used' => 0], $sheet->slots()),
        ])->save();

        return $sheet;
    }
}
