<?php

namespace App\Actions\Sessions;

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateSession
{
    /**
     * Only keys present in $data change.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(GameSession $session, User $actor, array $data): GameSession
    {
        return DB::transaction(function () use ($session, $actor, $data): GameSession {
            $attributes = collect($data)
                ->only([
                    'number', 'title', 'scheduled_at', 'status', 'visibility',
                    'strong_start', 'live_notes', 'recap', 'recap_published_at', 'dm_notes',
                ])
                ->all();

            $session->fill($attributes);
            $session->updated_by = $actor->id;
            $session->save();

            return $session;
        });
    }
}
