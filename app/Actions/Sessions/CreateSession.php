<?php

namespace App\Actions\Sessions;

use App\Enums\SessionStatus;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
     * Each attempt gets its own transaction, which is a savepoint when a caller already
     * has one open.
     *
     * PostgreSQL aborts the whole transaction on any error and refuses every later
     * statement until it ends, so without the savepoint the retry's number lookup dies
     * with SQLSTATE 25P02. SQLite does not behave that way, which is how this survived
     * a green local suite and was caught by the PostgreSQL CI job.
     *
     * A savepoint rollback only undoes this attempt. The number another GM committed on
     * their own connection survives it, so the retry still reads their row and moves on.
     *
     * @param  array<string, mixed>  $data
     */
    private function store(Campaign $campaign, User $actor, array $data, ?int $number): GameSession
    {
        return DB::transaction(fn (): GameSession => $campaign->gameSessions()->create([
            'number' => $number ?? $this->nextNumber($campaign),
            'title' => $data['title'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => $data['status'] ?? SessionStatus::Planned,
            'visibility' => $data['visibility'] ?? Visibility::Players,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]));
    }
}
