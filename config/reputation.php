<?php

return [

    /*
    |--------------------------------------------------------------------------
    | What a standing is called
    |--------------------------------------------------------------------------
    |
    | A faction's standing with the party is the sum of the changes the GM
    | recorded, and these are the words demgem prints for a sum. They are
    | demgem's own: no published book is quoted, and a GM who wants Cordial
    | and Wary instead edits this file.
    |
    | Each band names the lowest sum it covers. Sums are read from the top down,
    | so a sum of 4 is Allied and a sum of -1 is Unfriendly.
    |
    */

    'bands' => [
        ['min' => 3, 'label' => 'Allied', 'variant' => 'success'],
        ['min' => 1, 'label' => 'Friendly', 'variant' => 'accent'],
        ['min' => 0, 'label' => 'Neutral', 'variant' => 'neutral'],
        ['min' => -2, 'label' => 'Unfriendly', 'variant' => 'danger'],
        ['min' => PHP_INT_MIN, 'label' => 'Hostile', 'variant' => 'danger'],
    ],

    /*
    |--------------------------------------------------------------------------
    | How far one moment moves it
    |--------------------------------------------------------------------------
    */

    'max_delta' => 5,

];
