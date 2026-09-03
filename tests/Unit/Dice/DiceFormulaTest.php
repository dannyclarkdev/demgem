<?php

use App\Exceptions\InvalidDiceFormulaException;
use App\Support\Dice\DiceFormula;
use App\Support\Dice\KeepMode;

it('parses the whole grammar', function (string $input, string $normalized, int $terms, int $modifier) {
    $formula = DiceFormula::parse($input);

    expect($formula->normalized)->toBe($normalized)
        ->and($formula->terms)->toHaveCount($terms)
        ->and($formula->modifier)->toBe($modifier);
})->with([
    ['d20', '1d20', 1, 0],
    ['1d20', '1d20', 1, 0],
    ['2d6+3', '2d6+3', 1, 3],
    ['2d6-3', '2d6-3', 1, -3],
    ['4d6kh3', '4d6kh3', 1, 0],
    ['2d20kl1', '2d20kl1', 1, 0],
    ['2d20kh', '2d20kh1', 1, 0],
    ['1d8+1d6+2', '1d8+1d6+2', 2, 2],
    ['1d8-1d4', '1d8-1d4', 2, 0],
    ['d100', '1d100', 1, 0],
    ['3+2d6', '2d6+3', 1, 3],
]);

it('ignores case and whitespace', function () {
    expect(DiceFormula::parse('  4 D 6 KH 3  ')->normalized)->toBe('4d6kh3')
        ->and(DiceFormula::parse('2D20 + 5')->normalized)->toBe('2d20+5');
});

it('reads the keep mode and count', function () {
    $formula = DiceFormula::parse('4d6kl2');

    expect($formula->terms[0]->keep)->toBe(KeepMode::Lowest)
        ->and($formula->terms[0]->keepCount)->toBe(2)
        ->and($formula->terms[0]->kept())->toBe(2);
});

it('rejects a formula with no dice', function (string $input) {
    expect(fn () => DiceFormula::parse($input))->toThrow(InvalidDiceFormulaException::class);
})->with(['', '   ', '5', '3+2', '+']);

it('rejects nonsense', function (string $input) {
    expect(fn () => DiceFormula::parse($input))->toThrow(InvalidDiceFormulaException::class);
})->with(['drop table users', '2x6', 'd', '2d', 'adv', '1d6**2', '2d6 or 3d6']);

it('caps the number of sides', function () {
    expect(DiceFormula::parse('1d1000')->normalized)->toBe('1d1000');

    expect(fn () => DiceFormula::parse('1d1001'))->toThrow(InvalidDiceFormulaException::class)
        ->and(fn () => DiceFormula::parse('1d1'))->toThrow(InvalidDiceFormulaException::class)
        ->and(fn () => DiceFormula::parse('1d0'))->toThrow(InvalidDiceFormulaException::class);
});

it('caps the number of dice in one term and across the formula', function () {
    expect(DiceFormula::parse('100d6')->totalDice())->toBe(100);

    expect(fn () => DiceFormula::parse('101d6'))->toThrow(InvalidDiceFormulaException::class)
        ->and(fn () => DiceFormula::parse('60d6+60d6'))->toThrow(InvalidDiceFormulaException::class)
        ->and(fn () => DiceFormula::parse('0d6'))->toThrow(InvalidDiceFormulaException::class);
});

it('refuses the denial-of-service formula', function () {
    expect(fn () => DiceFormula::parse('999d999'))->toThrow(InvalidDiceFormulaException::class);
});

it('caps the number of terms', function () {
    expect(fn () => DiceFormula::parse(implode('+', array_fill(0, 11, '1d6'))))
        ->toThrow(InvalidDiceFormulaException::class);
});

it('refuses to keep more dice than were rolled', function () {
    expect(fn () => DiceFormula::parse('2d20kh3'))->toThrow(InvalidDiceFormulaException::class)
        ->and(fn () => DiceFormula::parse('2d20kh0'))->toThrow(InvalidDiceFormulaException::class);
});

it('rewrites a leading die for advantage and disadvantage', function () {
    expect(DiceFormula::withAdvantage('d20', KeepMode::Highest))->toBe('2d20kh1')
        ->and(DiceFormula::withAdvantage('d20+5', KeepMode::Lowest))->toBe('2d20kl1+5')
        ->and(DiceFormula::withAdvantage('1d20+3', KeepMode::Highest))->toBe('2d20kh1+3')
        ->and(DiceFormula::withAdvantage('d20', null))->toBe('d20');
});

it('only rewrites the first die for advantage', function () {
    expect(DiceFormula::withAdvantage('1d20+1d4', KeepMode::Highest))->toBe('2d20kh1+1d4');
});

it('writes messages for someone holding dice', function () {
    // 999d999 trips the dice-count cap first, which is the more useful complaint.
    expect(fn () => DiceFormula::parse('999d999'))
        ->toThrow(InvalidDiceFormulaException::class, 'Roll between 1 and 100 dice at a time.');

    expect(fn () => DiceFormula::parse('1d1001'))
        ->toThrow(InvalidDiceFormulaException::class, 'A die needs between 2 and 1000 sides.');

    expect(fn () => DiceFormula::parse(''))
        ->toThrow(InvalidDiceFormulaException::class, 'Type a formula, like 2d6+3.');
});
