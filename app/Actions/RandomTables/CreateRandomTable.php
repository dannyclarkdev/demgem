<?php

namespace App\Actions\RandomTables;

use App\Models\Campaign;
use App\Models\RandomTable;
use App\Models\User;

class CreateRandomTable
{
    public function handle(Campaign $campaign, User $actor, string $name, ?string $description = null): RandomTable
    {
        return RandomTable::create([
            'campaign_id' => $campaign->id,
            'name' => trim($name),
            'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
            'created_by' => $actor->id,
        ]);
    }
}
