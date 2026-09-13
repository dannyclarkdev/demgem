<?php

use App\Support\Sheets\FifthEdition;

/**
 * The maths of the sheet, with no model in it. A modifier is the score, a
 * proficiency bonus is the level, and every bonus on the sheet is those two.
 */
it('reads a modifier off a score', function (int $score, int $modifier) {
    expect(FifthEdition::modifier($score))->toBe($modifier);
})->with([
    [1, -5], [8, -1], [9, -1], [10, 0], [11, 0], [12, 1], [17, 3], [20, 5], [30, 10],
]);

it('reads the proficiency bonus off the level', function (int $level, int $bonus) {
    expect(FifthEdition::proficiencyBonus($level))->toBe($bonus);
})->with([
    [1, 2], [4, 2], [5, 3], [8, 3], [9, 4], [12, 4], [13, 5], [16, 5], [17, 6], [20, 6],
    // A character with no level yet computes at level 1.
    [0, 2],
]);

it('adds the proficiency bonus once for a proficiency and twice for expertise', function () {
    expect(FifthEdition::skillBonus(17, false, false, 5))->toBe(3)
        ->and(FifthEdition::skillBonus(17, true, false, 5))->toBe(6)
        ->and(FifthEdition::skillBonus(17, true, true, 5))->toBe(9)
        // Expertise without proficiency is a form mistake, and it reads as proficiency.
        ->and(FifthEdition::skillBonus(17, false, true, 5))->toBe(6)
        ->and(FifthEdition::saveBonus(14, true, 9))->toBe(6)
        ->and(FifthEdition::saveBonus(14, false, 9))->toBe(2);
});

it('prints a bonus with its sign', function () {
    expect(FifthEdition::signed(3))->toBe('+3')
        ->and(FifthEdition::signed(-1))->toBe('-1')
        ->and(FifthEdition::signed(0))->toBe('+0');
});

it('gives back half the hit dice on a long rest, and at least one', function () {
    expect(FifthEdition::hitDiceAfterLongRest(5, 5))->toBe(3)
        ->and(FifthEdition::hitDiceAfterLongRest(1, 1))->toBe(0)
        ->and(FifthEdition::hitDiceAfterLongRest(0, 5))->toBe(0)
        ->and(FifthEdition::hitDiceAfterLongRest(9, 9))->toBe(5);
});

it('knows eighteen skills, each on one of the six abilities', function () {
    expect(FifthEdition::SKILLS)->toHaveCount(18)
        ->and(FifthEdition::ABILITIES)->toHaveCount(6)
        ->and(collect(FifthEdition::SKILLS)->pluck('ability')->unique()->diff(array_keys(FifthEdition::ABILITIES))->all())->toBe([])
        ->and(FifthEdition::SKILLS['stealth'])->toBe(['name' => 'Stealth', 'ability' => 'dex'])
        ->and(FifthEdition::SKILLS['sleight_of_hand']['name'])->toBe('Sleight of Hand');
});
