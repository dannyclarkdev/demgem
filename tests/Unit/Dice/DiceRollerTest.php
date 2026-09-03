<?php

use App\Support\Dice\DiceFormula;
use App\Support\Dice\DiceRoller;
use Random\Engine\Mt19937;
use Random\Randomizer;

function seededRoller(int $seed = 1234): DiceRoller
{
    return new DiceRoller(new Randomizer(new Mt19937($seed)));
}

it('rolls the same formula the same way from the same seed', function () {
    $first = seededRoller()->roll(DiceFormula::parse('4d6kh3'));
    $second = seededRoller()->roll(DiceFormula::parse('4d6kh3'));

    expect($first->total)->toBe($second->total)
        ->and($first->terms[0]['faces'])->toBe($second->terms[0]['faces']);
});

it('adds the modifier to the dice', function () {
    $roll = seededRoller()->roll(DiceFormula::parse('2d6+3'));
    $faces = $roll->terms[0]['faces'];

    expect($roll->total)->toBe(array_sum($faces) + 3)
        ->and($roll->modifier)->toBe(3)
        ->and($faces)->toHaveCount(2);
});

it('subtracts a negative term', function () {
    $roll = seededRoller()->roll(DiceFormula::parse('1d8-1d4'));

    expect($roll->total)->toBe($roll->terms[0]['subtotal'] + $roll->terms[1]['subtotal'])
        ->and($roll->terms[1]['sign'])->toBe(-1)
        ->and($roll->terms[1]['subtotal'])->toBeLessThanOrEqual(-1);
});

it('keeps the highest dice and drops the rest', function () {
    $roll = seededRoller()->roll(DiceFormula::parse('4d6kh3'));
    $kept = $roll->terms[0]['faces'];
    $dropped = $roll->terms[0]['dropped'];

    expect($kept)->toHaveCount(3)
        ->and($dropped)->toHaveCount(1)
        ->and(min($kept))->toBeGreaterThanOrEqual(max($dropped))
        ->and($roll->total)->toBe(array_sum($kept));
});

it('keeps the lowest die for disadvantage', function () {
    $roll = seededRoller()->roll(DiceFormula::parse('2d20kl1'));
    $kept = $roll->terms[0]['faces'];
    $dropped = $roll->terms[0]['dropped'];

    expect($kept)->toHaveCount(1)
        ->and($dropped)->toHaveCount(1)
        ->and($kept[0])->toBeLessThanOrEqual($dropped[0])
        ->and($roll->total)->toBe($kept[0]);
});

it('keeps the faces in the order they landed', function () {
    $roll = seededRoller()->roll(DiceFormula::parse('4d6'));

    expect($roll->terms[0]['faces'])->toHaveCount(4)
        ->and($roll->terms[0]['dropped'])->toBe([])
        ->and($roll->allFaces())->toHaveCount(4);
});

it('stays inside the range of the die over many rolls', function () {
    $roller = seededRoller(99);

    for ($i = 0; $i < 200; $i++) {
        $roll = $roller->roll(DiceFormula::parse('1d6'));

        expect($roll->total)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(6);
    }
});

it('can reach both ends of a die', function () {
    $roller = seededRoller(7);
    $seen = [];

    for ($i = 0; $i < 400; $i++) {
        $seen[$roller->roll(DiceFormula::parse('1d4'))->total] = true;
    }

    expect(array_keys($seen))->toEqualCanonicalizing([1, 2, 3, 4]);
});

it('records the normalised formula on the result', function () {
    expect(seededRoller()->roll(DiceFormula::parse(' 2 D 20 KH 1 '))->formula)->toBe('2d20kh1');
});
