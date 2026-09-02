<?php

namespace Database\Factories;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignMember>
 */
class CampaignMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'user_id' => User::factory(),
            'role' => CampaignRole::Player,
        ];
    }

    public function role(CampaignRole $role): static
    {
        return $this->state(['role' => $role]);
    }
}
