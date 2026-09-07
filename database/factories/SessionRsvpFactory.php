<?php

namespace Database\Factories;

use App\Enums\Rsvp;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\SessionRsvp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionRsvp>
 */
class SessionRsvpFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'game_session_id' => GameSession::factory(),
            'user_id' => User::factory(),
            'rsvp' => Rsvp::Yes,
            'attended' => null,
        ];
    }

    public function forSession(GameSession $session, User $user): static
    {
        return $this->state([
            'campaign_id' => $session->campaign_id,
            'game_session_id' => $session->id,
            'user_id' => $user->id,
        ]);
    }

    public function saying(Rsvp $rsvp): static
    {
        return $this->state(['rsvp' => $rsvp]);
    }

    public function attended(bool $attended = true): static
    {
        return $this->state(['attended' => $attended]);
    }
}
