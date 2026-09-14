<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Who may make an account
    |--------------------------------------------------------------------------
    |
    | "invite", the default, opens the register page to two people only: whoever
    | holds a valid invite link, and the first person to reach an install that
    | has no users yet. "open" is a public register page. Anything else reads as
    | "invite". Support\Auth\RegistrationGate is the one place that asks.
    |
    */

    'mode' => env('DEMGEM_REGISTRATION', 'invite'),

];
