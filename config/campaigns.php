<?php

return [

    /*
    |--------------------------------------------------------------------------
    | How much a campaign may hold
    |--------------------------------------------------------------------------
    |
    | One ceiling for every campaign on this install, in megabytes, over every
    | file a campaign owns: its cover, each entity's image, each handout's files.
    | The measure is summed from the media rows on every read and never stored.
    |
    | Zero turns the check and the bar off, for an install that wants no ceiling.
    |
    */

    'storage_limit_mb' => (float) env('CAMPAIGN_STORAGE_MB', 500),

];
