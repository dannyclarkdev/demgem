<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => SocialAccount::DISCORD,
            'provider_id' => (string) fake()->unique()->numberBetween(100000000000000000, 999999999999999999),
            'name' => fake()->userName(),
            'avatar_url' => null,
        ];
    }

    public function discord(string $providerId): static
    {
        return $this->state(['provider' => SocialAccount::DISCORD, 'provider_id' => $providerId]);
    }
}
