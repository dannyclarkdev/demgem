<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\SessionDateOption;
use App\Models\SessionDateVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionDateVote>
 */
class SessionDateVoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'session_date_option_id' => SessionDateOption::factory(),
            'user_id' => User::factory(),
        ];
    }

    public function forOption(SessionDateOption $option, User $user): static
    {
        return $this->state([
            'campaign_id' => $option->campaign_id,
            'session_date_option_id' => $option->id,
            'user_id' => $user->id,
        ]);
    }
}
