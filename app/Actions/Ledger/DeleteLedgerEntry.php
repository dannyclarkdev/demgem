<?php

namespace App\Actions\Ledger;

use App\Models\LedgerEntry;

class DeleteLedgerEntry
{
    public function handle(LedgerEntry $entry): void
    {
        $entry->delete();
    }
}
