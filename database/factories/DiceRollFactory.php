<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\DiceRoll;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiceRoll>
 */
class DiceRollFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $face = fake()->numberBetween(1, 20);

        return [
            'campaign_id' => Campaign::factory(),
            'game_session_id' => null,
            'user_id' => User::factory(),
            'formula' => '1d20',
            'label' => null,
            'total' => $face,
            'detail' => [
                'terms' => [[
                    'expression' => '1d20',
                    'sign' => 1,
                    'faces' => [$face],
                    'dropped' => [],
                    'subtotal' => $face,
                ]],
                'modifier' => 0,
            ],
            'private' => false,
        ];
    }

    public function inSession(GameSession $session): static
    {
        return $this->state([
            'campaign_id' => $session->campaign_id,
            'game_session_id' => $session->id,
        ]);
    }

    public function by(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    /**
     * A roll behind the screen. Only the person who made it reads it.
     */
    public function behindTheScreen(): static
    {
        return $this->state(['private' => true]);
    }
}
