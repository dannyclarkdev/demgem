<?php

namespace App\Support\Dice;

use Random\Randomizer;

/**
 * Rolls a parsed formula. The Randomizer is injected so a test can bind a seeded
 * engine and assert exact totals instead of ranges.
 */
class DiceRoller
{
    public function __construct(private readonly Randomizer $randomizer) {}

    public function roll(DiceFormula $formula): DiceRoll
    {
        $total = $formula->modifier;
        $terms = [];

        foreach ($formula->terms as $term) {
            $rolled = [];

            for ($die = 0; $die < $term->count; $die++) {
                $rolled[] = $this->randomizer->getInt(1, $term->sides);
            }

            [$kept, $dropped] = $this->applyKeep($term, $rolled);
            $subtotal = $term->sign * array_sum($kept);
            $total += $subtotal;

            $terms[] = [
                'expression' => $term->expression(),
                'sign' => $term->sign,
                'faces' => $kept,
                'dropped' => $dropped,
                'subtotal' => $subtotal,
            ];
        }

        return new DiceRoll($formula->normalized, $total, $terms, $formula->modifier);
    }

    /**
     * Splits the rolled faces into the ones that count and the ones that do not. Both
     * are kept in rolled order, so the GM sees the dice as they landed.
     *
     * @param  list<int>  $rolled
     * @return array{0: list<int>, 1: list<int>}
     */
    private function applyKeep(DiceTerm $term, array $rolled): array
    {
        if ($term->keep === null) {
            return [$rolled, []];
        }

        $indexed = $rolled;
        $term->keep === KeepMode::Highest
            ? arsort($indexed)
            : asort($indexed);

        $keptIndexes = array_slice(array_keys($indexed), 0, $term->kept(), true);
        $kept = [];
        $dropped = [];

        foreach ($rolled as $index => $face) {
            in_array($index, $keptIndexes, true)
                ? $kept[] = $face
                : $dropped[] = $face;
        }

        return [$kept, $dropped];
    }
}
