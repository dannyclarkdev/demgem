<?php

namespace App\Support\Dice;

/**
 * The result of one roll, with every face kept. A GM wants to see the dice, not just
 * the number, and a dropped die is part of the story of a 4d6kh3.
 */
readonly class DiceRoll
{
    /**
     * @param  list<array{expression: string, sign: int, faces: list<int>, dropped: list<int>, subtotal: int}>  $terms
     */
    public function __construct(
        public string $formula,
        public int $total,
        public array $terms,
        public int $modifier,
    ) {}

    /**
     * Stored in dice_rolls.detail.
     *
     * @return array{terms: list<array{expression: string, sign: int, faces: list<int>, dropped: list<int>, subtotal: int}>, modifier: int}
     */
    public function toArray(): array
    {
        return [
            'terms' => $this->terms,
            'modifier' => $this->modifier,
        ];
    }

    /**
     * Every face rolled, in term order, for a one-line summary.
     *
     * @return list<int>
     */
    public function allFaces(): array
    {
        $faces = [];

        foreach ($this->terms as $term) {
            foreach ([...$term['faces'], ...$term['dropped']] as $face) {
                $faces[] = $face;
            }
        }

        return $faces;
    }
}
