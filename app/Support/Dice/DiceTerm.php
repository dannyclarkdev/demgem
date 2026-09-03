<?php

namespace App\Support\Dice;

/**
 * One dice term of a formula: 2d6, d20, 4d6kh3, or a negated 1d4.
 */
readonly class DiceTerm
{
    public function __construct(
        public int $count,
        public int $sides,
        public int $sign = 1,
        public ?KeepMode $keep = null,
        public int $keepCount = 1,
    ) {}

    /**
     * How many dice actually count towards the total.
     */
    public function kept(): int
    {
        return $this->keep === null ? $this->count : min($this->keepCount, $this->count);
    }

    /**
     * The term as it will be stored and shown back, which is not always as it was typed.
     */
    public function expression(): string
    {
        $keep = $this->keep === null ? '' : $this->keep->value.$this->keepCount;

        return ($this->sign < 0 ? '-' : '').$this->count.'d'.$this->sides.$keep;
    }
}
