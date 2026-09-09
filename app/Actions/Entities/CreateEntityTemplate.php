<?php

namespace App\Actions\Entities;

use App\Enums\EntityType;
use App\Models\Campaign;
use App\Models\EntityTemplate;

class CreateEntityTemplate
{
    public function handle(Campaign $campaign, EntityType $type, string $name, ?string $body): EntityTemplate
    {
        return EntityTemplate::query()->create([
            'campaign_id' => $campaign->id,
            'type' => $type,
            'name' => trim($name),
            'body' => $body === '' ? null : $body,
        ]);
    }
}
