<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\GameSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GameSession>
 */
class GameSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'number' => fake()->unique()->numberBetween(1, 9000),
            'title' => Str::title(fake()->words(3, true)),
            'scheduled_at' => now()->addWeek(),
            'status' => SessionStatus::Planned,
            'visibility' => Visibility::Players,
            'strong_start' => null,
            'live_notes' => null,
            'recap' => null,
            'recap_published_at' => null,
            'dm_notes' => null,
        ];
    }

    public function number(int $number): static
    {
        return $this->state(['number' => $number]);
    }

    public function planned(): static
    {
        return $this->state(['status' => SessionStatus::Planned]);
    }

    public function played(): static
    {
        return $this->state(['status' => SessionStatus::Played, 'scheduled_at' => now()->subWeek()]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => SessionStatus::Cancelled]);
    }

    /**
     * A draft the party cannot see yet.
     */
    public function hidden(): static
    {
        return $this->state(['visibility' => Visibility::Dm]);
    }

    public function unscheduled(): static
    {
        return $this->state(['scheduled_at' => null]);
    }

    /**
     * Planned, dated, and the date has passed.
     */
    public function overdue(): static
    {
        return $this->state(['status' => SessionStatus::Planned, 'scheduled_at' => now()->subDays(3)]);
    }

    public function withRecap(string $recap = 'The party burned the bridge.'): static
    {
        return $this->played()->state(['recap' => $recap]);
    }

    public function published(string $recap = 'The party burned the bridge.'): static
    {
        return $this->withRecap($recap)->state(['recap_published_at' => now()]);
    }

    public function withPrep(): static
    {
        return $this->state([
            'strong_start' => 'The bell above the tavern door rings, and nobody opened it.',
            'dm_notes' => 'Keep the duke off screen tonight.',
        ]);
    }
}
