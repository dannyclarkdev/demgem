<?php

namespace App\Actions\Encounters;

use App\Events\EncounterChanged;
use App\Models\Combatant;

class ApplyDamage
{
    /**
     * One signed amount: positive damages, negative heals.
     *
     * HP clamps at 0 and at max_hp when one is set. What else happens is the fight's
     * four rules, and all of them hang off this one number so a GM never has to
     * remember a second click:
     *
     *   - Damage on a row already on nought is a failed death save.
     *   - Dropping to nought ends concentration and starts the death saves at none.
     *   - Healing back above nought clears the marks, because the question is over.
     *   - Damage on a concentrating row that is still standing needs a save, and the
     *     DC is what this returns. Nothing is rolled here: rolling a creature's save
     *     means owning its modifiers, and the dataset prints those for a reader.
     *
     * @return int|null The concentration DC the GM now has to roll against, if any.
     */
    public function handle(Combatant $combatant, int $amount): ?int
    {
        if ($combatant->hp === null) {
            return null;
        }

        $wasDown = $combatant->isDown();

        $hp = $combatant->hp - $amount;
        $hp = max(0, $hp);

        if ($combatant->max_hp !== null) {
            $hp = min($hp, $combatant->max_hp);
        }

        $changes = ['hp' => $hp];
        $isDamage = $amount > 0;
        $nowDown = $hp <= 0;

        if ($nowDown && ! $wasDown) {
            $changes['concentrating_on'] = null;
            $changes['death_save_successes'] = 0;
            $changes['death_save_failures'] = 0;
        }

        if ($isDamage && $wasDown && $nowDown) {
            $changes['death_save_failures'] = min(
                Combatant::DEATH_SAVES,
                $combatant->death_save_failures + 1,
            );
        }

        if (! $nowDown && $wasDown) {
            $changes['death_save_successes'] = 0;
            $changes['death_save_failures'] = 0;
        }

        $needsSave = $isDamage && ! $nowDown && $combatant->isConcentrating();

        $combatant->update($changes);

        EncounterChanged::dispatch($combatant->campaign_id, $combatant->encounter_id);

        return $needsSave ? self::concentrationDc($amount) : null;
    }

    /**
     * Ten, or half the damage, whichever is more.
     *
     * The number is a fact and it is computed rather than quoted. The paragraph the
     * book prints around it is licensed text and is not copied anywhere in demgem.
     */
    public static function concentrationDc(int $damage): int
    {
        return max(10, intdiv(abs($damage), 2));
    }
}
