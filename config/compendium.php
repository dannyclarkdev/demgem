<?php

use App\Enums\Ruleset;

return [

    /*
    |--------------------------------------------------------------------------
    | The shipped dataset
    |--------------------------------------------------------------------------
    |
    | demgem:import-srd reads this file and nothing else writes stat_blocks. The
    | checksum is the guard: CompendiumDatasetTest compares it against the file on
    | disk, so replacing the data is a deliberate commit rather than an accident.
    | Only SRD content belongs here. See database/srd/README.md.
    |
    */

    'ruleset' => Ruleset::Srd5e2024->value,

    'dataset' => database_path('srd/srd-5.2.1-creatures.json'),

    'dataset_sha256' => '6ec28765f6babc5f25f71d96fc6bbae54e008d241b12457a68de48e07ea8d30a',

    /*
    |--------------------------------------------------------------------------
    | Attribution
    |--------------------------------------------------------------------------
    |
    | CC BY 4.0 asks for credit wherever the material is shown. This is the string
    | the compendium screens and the API both render, so it is written once here.
    | database/srd/ATTRIBUTION.md says where it has to appear and why.
    |
    */

    'source' => 'SRD 5.2.1',

    'license' => 'CC-BY-4.0',

    'license_url' => 'https://creativecommons.org/licenses/by/4.0/legalcode',

    'attribution' => 'This work includes material taken from the System Reference Document 5.2.1 ("SRD 5.2.1") by Wizards of the Coast LLC and is licensed under the Creative Commons Attribution 4.0 International License, available at https://creativecommons.org/licenses/by/4.0/legalcode.',

];
