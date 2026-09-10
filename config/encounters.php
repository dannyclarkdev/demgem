<?php

return [

    /*
    |--------------------------------------------------------------------------
    | What a party can afford
    |--------------------------------------------------------------------------
    |
    | These numbers are demgem's own. The SRD 5.2.1 gives every creature an XP
    | value and that data ships with the app, but it does not publish an encounter
    | building budget, and the book that does is not ours to copy.
    |
    | So demgem states one rule and derives the rest from data it already has:
    |
    |   A character of level N can face a Low fight worth a quarter of the XP of
    |   a CR N creature, a Moderate fight worth half of it, and a High fight worth
    |   three quarters. A party's budget is the sum over its characters.
    |
    | One creature of a party's own level being roughly a hard fight for four of
    | them is the observation the fractions come from, and the read-out was checked
    | against real parties at levels 1, 3, 10 and 20 before it shipped.
    |
    | Nothing here claims a published book agrees. The read-out on the screen names
    | the rule so a GM who disagrees knows exactly what to ignore.
    |
    */

    'bands' => [
        'low' => 0.25,
        'moderate' => 0.5,
        'high' => 0.75,
    ],

    /*
    |--------------------------------------------------------------------------
    | Challenge rating to experience points
    |--------------------------------------------------------------------------
    |
    | Read out of the shipped SRD dataset rather than typed: at each challenge
    | rating this is the XP value most of that rating's creatures print, ties going
    | to the higher. That resolves the two rows that disagree with their
    | neighbours, the zero-XP Seahorse and Shrieker Fungus at CR 0, and the
    | Archmage at CR 12.
    |
    | EncounterBudgetTest reads the dataset and fails when this table stops
    | agreeing with it, so replacing the data cannot silently move the budget.
    |
    | The SRD names no creature at CR 18, or between 25 and 29, so those rungs are
    | absent. Budget::xpForChallenge() reads between two rungs in a straight line,
    | which puts CR 18 at 20,000 where the ladder's own shape says it belongs.
    |
    */

    'ladder' => [
        '0' => 10,
        '0.125' => 25,
        '0.25' => 50,
        '0.5' => 100,
        '1' => 200,
        '2' => 450,
        '3' => 700,
        '4' => 1100,
        '5' => 1800,
        '6' => 2300,
        '7' => 2900,
        '8' => 3900,
        '9' => 5000,
        '10' => 5900,
        '11' => 7200,
        '12' => 8400,
        '13' => 10000,
        '14' => 11500,
        '15' => 13000,
        '16' => 15000,
        '17' => 18000,
        '19' => 22000,
        '20' => 25000,
        '21' => 33000,
        '22' => 41000,
        '23' => 50000,
        '24' => 62000,
        '30' => 155000,
    ],

];
