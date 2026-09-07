<?php

namespace App\Actions\Sessions;

use App\Models\SessionDateOption;

class RemoveDateOption
{
    /**
     * The votes cascade with it.
     */
    public function handle(SessionDateOption $option): void
    {
        $option->delete();
    }
}
