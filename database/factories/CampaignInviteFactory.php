<?php

namespace Database\Factories;

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\CampaignInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CampaignInvite>
 */
class CampaignInviteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'token' => Str::random(40),
            'role' => CampaignRole::Player,
            'max_uses' => null,
            'uses' => 0,
            'expires_at' => null,
            'revoked_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function role(CampaignRole $role): static
    {
        return $this->state(['role' => $role]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function exhausted(): static
    {
        return $this->state(['max_uses' => 1, 'uses' => 1]);
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => now()]);
    }

    public function singleUse(): static
    {
        return $this->state(['max_uses' => 1]);
    }
}
