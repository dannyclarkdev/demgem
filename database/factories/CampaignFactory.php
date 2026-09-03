<?php

namespace Database\Factories;

use App\Enums\CampaignRole;
use App\Enums\Ruleset;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->unique()->words(3, true)),
            'description' => fake()->sentence(),
            'ruleset' => Ruleset::Generic,
            'timezone' => 'UTC',
            'created_by' => User::factory(),
        ];
    }

    /**
     * Every campaign has exactly one owner: the creator, unless a state replaced it.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Campaign $campaign): void {
            if ($campaign->created_by !== null && ! $campaign->owner()->exists()) {
                $campaign->members()->create([
                    'user_id' => $campaign->created_by,
                    'role' => CampaignRole::Owner,
                ]);
            }
        });
    }

    public function ownedBy(User $user): static
    {
        return $this->state(['created_by' => $user->id]);
    }

    public function withMember(User $user, CampaignRole $role = CampaignRole::Player): static
    {
        return $this->afterCreating(function (Campaign $campaign) use ($user, $role): void {
            $campaign->members()->create(['user_id' => $user->id, 'role' => $role]);
        });
    }
}
