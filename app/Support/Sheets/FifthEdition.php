<?php

namespace App\Support\Sheets;

/**
 * The maths of the SRD 5.2.1 character sheet, with no model in it.
 *
 * A modifier is the score, a proficiency bonus is the level, and every bonus on the
 * sheet is one of those or both. None of it is stored: the sheet keeps what a
 * player decides and this class computes what the rules decide, on every read.
 *
 * The ability and skill names are SRD terms, and the card that prints them carries
 * the compendium's attribution notice for that reason.
 */
final class FifthEdition
{
    public const MIN_SCORE = 1;

    public const MAX_SCORE = 30;

    public const MAX_HIT_POINTS = 999;

    public const MAX_LEVEL = 100;

    public const MAX_SLOT_LEVEL = 9;

    public const MAX_SLOTS = 9;

    public const MAX_ARMOR_CLASS = 40;

    public const MAX_SPEED = 999;

    /** @var list<int> */
    public const HIT_DICE = [6, 8, 10, 12];

    /** @var array<string, string> */
    public const ABILITIES = [
        'str' => 'Strength',
        'dex' => 'Dexterity',
        'con' => 'Constitution',
        'int' => 'Intelligence',
        'wis' => 'Wisdom',
        'cha' => 'Charisma',
    ];

    /** @var array<string, array{name: string, ability: string}> */
    public const SKILLS = [
        'acrobatics' => ['name' => 'Acrobatics', 'ability' => 'dex'],
        'animal_handling' => ['name' => 'Animal Handling', 'ability' => 'wis'],
        'arcana' => ['name' => 'Arcana', 'ability' => 'int'],
        'athletics' => ['name' => 'Athletics', 'ability' => 'str'],
        'deception' => ['name' => 'Deception', 'ability' => 'cha'],
        'history' => ['name' => 'History', 'ability' => 'int'],
        'insight' => ['name' => 'Insight', 'ability' => 'wis'],
        'intimidation' => ['name' => 'Intimidation', 'ability' => 'cha'],
        'investigation' => ['name' => 'Investigation', 'ability' => 'int'],
        'medicine' => ['name' => 'Medicine', 'ability' => 'wis'],
        'nature' => ['name' => 'Nature', 'ability' => 'int'],
        'perception' => ['name' => 'Perception', 'ability' => 'wis'],
        'performance' => ['name' => 'Performance', 'ability' => 'cha'],
        'persuasion' => ['name' => 'Persuasion', 'ability' => 'cha'],
        'religion' => ['name' => 'Religion', 'ability' => 'int'],
        'sleight_of_hand' => ['name' => 'Sleight of Hand', 'ability' => 'dex'],
        'stealth' => ['name' => 'Stealth', 'ability' => 'dex'],
        'survival' => ['name' => 'Survival', 'ability' => 'wis'],
    ];

    public static function modifier(int $score): int
    {
        return (int) floor(($score - 10) / 2);
    }

    /**
     * Two at level one, and one more every four levels. A character with no level
     * yet computes at level one.
     */
    public static function proficiencyBonus(int $level): int
    {
        return 2 + intdiv(max(1, $level) - 1, 4);
    }

    public static function saveBonus(int $score, bool $proficient, int $level): int
    {
        return self::modifier($score) + ($proficient ? self::proficiencyBonus($level) : 0);
    }

    /**
     * Expertise doubles the proficiency bonus. Expertise without proficiency is a
     * form mistake, and it reads as proficiency rather than as nothing.
     */
    public static function skillBonus(int $score, bool $proficient, bool $expertise, int $level): int
    {
        $bonus = self::proficiencyBonus($level);

        return self::modifier($score) + match (true) {
            $proficient && $expertise => 2 * $bonus,
            $proficient || $expertise => $bonus,
            default => 0,
        };
    }

    /**
     * A long rest gives back half the hit dice, rounded down, and at least one.
     */
    public static function hitDiceAfterLongRest(int $spent, int $level): int
    {
        return max(0, $spent - max(1, intdiv(max(1, $level), 2)));
    }

    public static function signed(int $bonus): string
    {
        return ($bonus < 0 ? '' : '+').$bonus;
    }
}
