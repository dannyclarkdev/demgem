<?php

namespace App\Actions\Sessions;

use App\Enums\SessionStatus;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

class CreateSession
{
    /**
     * @param  array{
     *     number?: int|null,
     *     title?: string|null,
     *     scheduled_at?: Carbon|null,
     *     status?: SessionStatus,
     *     visibility?: Visibility,
     * }  $data
     */
    public function handle(Campaign $campaign, User $actor, array $data): GameSession
    {
        $chosenNumber = $data['number'] ?? null;

        try {
            return $this->store($campaign, $actor, $data, $chosenNumber);
        } catch (UniqueConstraintViolationException $exception) {
            // Two GMs created a session at the same moment. Retry once with a fresh
            // number. A GM who typed the number gets the error, because picking a
            // different one for them would be worse than telling them.
            if ($chosenNumber !== null) {
                throw $exception;
            }

            return $this->store($campaign, $actor, $data, null);
        }
    }

    /**
     * The next free number, counting trashed sessions so a restore never collides.
     */
    public function nextNumber(Campaign $campaign): int
    {
        $highest = GameSession::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->max('number');

        return $highest === null ? 1 : ((int) $highest) + 1;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * One insert, so no transaction. That also means a failed attempt rolls nothing
     * back and the retry above sees the number the other GM just took.
     *
     * @param  array<string, mixed>  $data
     */
    private function store(Campaign $campaign, User $actor, array $data, ?int $number): GameSession
    {
        return $campaign->gameSessions()->create([
            'number' => $number ?? $this->nextNumber($campaign),
            'title' => $data['title'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => $data['status'] ?? SessionStatus::Planned,
            'visibility' => $data['visibility'] ?? Visibility::Players,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }
}
