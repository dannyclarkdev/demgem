<?php

namespace App\Support\Dice;

use App\Exceptions\InvalidDiceFormulaException;

/**
 * One grammar, no aliases:
 *
 *   formula := term (('+' | '-') term)*
 *   term    := dice | integer
 *   dice    := [count] 'd' sides [('kh' | 'kl') [n]]
 *
 * 2d6+3, d20, 4d6kh3, 2d20kl1, 1d8+1d6+2 all parse. There is deliberately no "adv"
 * keyword: the advantage toggle rewrites a d20 into 2d20kh1 before parsing, because
 * two syntaxes for one roll is the thing to avoid.
 *
 * The limits live here rather than in the form, so the screen and anything else that
 * ever calls this share one answer about what is too big to roll.
 */
readonly class DiceFormula
{
    public const MAX_SIDES = 1000;

    public const MIN_SIDES = 2;

    public const MAX_COUNT = 100;

    public const MAX_TERMS = 10;

    public const MAX_DICE = 100;

    public const MAX_MODIFIER = 9999;

    /**
     * @param  list<DiceTerm>  $terms
     */
    private function __construct(
        public string $normalized,
        public array $terms,
        public int $modifier,
    ) {}

    /**
     * @throws InvalidDiceFormulaException
     */
    public static function parse(string $input): self
    {
        $source = (string) preg_replace('/\s+/u', '', mb_strtolower(trim($input)));

        if ($source === '') {
            throw new InvalidDiceFormulaException('Type a formula, like 2d6+3.');
        }

        if (preg_match('/^[0-9dkhl+-]+$/', $source) !== 1) {
            throw new InvalidDiceFormulaException('Use numbers, d, kh, kl, + and - only. For example 4d6kh3.');
        }

        $chunks = preg_split('/(?=[+-])/', $source, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($chunks) > self::MAX_TERMS) {
            throw new InvalidDiceFormulaException('That is more than '.self::MAX_TERMS.' terms. Roll it in two goes.');
        }

        $terms = [];
        $modifier = 0;
        $diceTotal = 0;

        foreach ($chunks as $chunk) {
            [$term, $flat] = self::parseChunk($chunk);

            if ($term !== null) {
                $diceTotal += $term->count;

                if ($diceTotal > self::MAX_DICE) {
                    throw new InvalidDiceFormulaException('That is more than '.self::MAX_DICE.' dice in one roll.');
                }

                $terms[] = $term;

                continue;
            }

            $modifier += $flat;
        }

        if ($terms === []) {
            throw new InvalidDiceFormulaException('A roll needs at least one die, like d20.');
        }

        if (abs($modifier) > self::MAX_MODIFIER) {
            throw new InvalidDiceFormulaException('That modifier is too large.');
        }

        return new self(self::normalize($terms, $modifier), $terms, $modifier);
    }

    /**
     * A leading d20 becomes 2d20kh1 or 2d20kl1. Applied before parsing so the grammar
     * never has to know what advantage means.
     */
    public static function withAdvantage(string $input, ?KeepMode $mode): string
    {
        if ($mode === null) {
            return $input;
        }

        return (string) preg_replace(
            '/^(\s*)(\d*)d(\d+)/i',
            '${1}2d${3}'.$mode->value.'1',
            trim($input),
            1,
        );
    }

    public function totalDice(): int
    {
        return array_sum(array_map(fn (DiceTerm $term) => $term->count, $this->terms));
    }

    /**
     * @return array{0: DiceTerm|null, 1: int}
     */
    private static function parseChunk(string $chunk): array
    {
        $pattern = '/^(?<sign>[+-])?(?:(?<count>\d*)d(?<sides>\d+)(?:(?<keep>kh|kl)(?<keepCount>\d*))?|(?<flat>\d+))$/';

        if (preg_match($pattern, $chunk, $match) !== 1) {
            throw new InvalidDiceFormulaException("Could not read \"{$chunk}\". Try something like 2d6+3.");
        }

        $sign = ($match['sign'] ?? '') === '-' ? -1 : 1;

        if (($match['flat'] ?? '') !== '') {
            return [null, $sign * (int) $match['flat']];
        }

        $count = $match['count'] === '' ? 1 : (int) $match['count'];
        $sides = (int) $match['sides'];

        if ($count < 1 || $count > self::MAX_COUNT) {
            throw new InvalidDiceFormulaException('Roll between 1 and '.self::MAX_COUNT.' dice at a time.');
        }

        if ($sides < self::MIN_SIDES || $sides > self::MAX_SIDES) {
            throw new InvalidDiceFormulaException('A die needs between '.self::MIN_SIDES.' and '.self::MAX_SIDES.' sides.');
        }

        $keep = ($match['keep'] ?? '') !== '' ? KeepMode::from($match['keep']) : null;
        $keepCount = 1;

        if ($keep !== null) {
            $keepCount = ($match['keepCount'] ?? '') === '' ? 1 : (int) $match['keepCount'];

            if ($keepCount < 1 || $keepCount > $count) {
                throw new InvalidDiceFormulaException("Keep between 1 and {$count} of {$count} dice.");
            }
        }

        return [new DiceTerm($count, $sides, $sign, $keep, $keepCount), 0];
    }

    /**
     * @param  list<DiceTerm>  $terms
     */
    private static function normalize(array $terms, int $modifier): string
    {
        $out = '';

        foreach ($terms as $index => $term) {
            $expression = $term->expression();

            $out .= $index === 0
                ? $expression
                : ($term->sign < 0 ? $expression : '+'.$expression);
        }

        if ($modifier !== 0) {
            $out .= ($modifier > 0 ? '+' : '-').abs($modifier);
        }

        return $out;
    }
}
