<?php

namespace App\Actions\Decisions;

use App\Models\Decision;

class DeleteDecision
{
    public function handle(Decision $decision): void
    {
        $decision->delete();
    }
}
