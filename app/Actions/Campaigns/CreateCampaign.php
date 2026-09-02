<?php

namespace App\Actions\Campaigns;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateCampaign
{
    /**
     * @param  array{name: string, description?: string|null, ruleset: string}  $attributes
     */
    public function handle(User $owner, array $attributes): Campaign
    {
        return DB::transaction(function () use ($owner, $attributes): Campaign {
            $campaign = Campaign::create([...$attributes, 'created_by' => $owner->id]);

            $campaign->members()->create([
                'user_id' => $owner->id,
                'role' => CampaignRole::Owner,
            ]);

            return $campaign;
        });
    }
}
