<?php

namespace App\Observers;

use App\Actions\Mentions\SyncMentions;
use App\Models\GameSession;
use App\Models\Mention;

class GameSessionObserver
{
    public function __construct(private readonly SyncMentions $syncMentions) {}

    public function saved(GameSession $session): void
    {
        if ($session->wasRecentlyCreated || $session->wasChanged($session->mentionableFields())) {
            $this->sync($session);
        }
    }

    public function restored(GameSession $session): void
    {
        $this->sync($session);
    }

    /**
     * A soft-deleted session keeps its mention rows, the way a soft-deleted entity does.
     * Every read filters trashed sources. A force delete has nothing to come back to, and
     * the database cascade removes scenes without firing their observer, so clean up here
     * while the scene rows still exist.
     */
    public function forceDeleting(GameSession $session): void
    {
        $sceneIds = $session->scenes()->pluck('id');

        if ($sceneIds->isNotEmpty()) {
            Mention::withoutGlobalScopes()
                ->where('source_type', 'scene')
                ->whereIn('source_id', $sceneIds)
                ->delete();
        }

        Mention::withoutGlobalScopes()
            ->where('source_type', $session->getMorphClass())
            ->where('source_id', $session->id)
            ->delete();
    }

    private function sync(GameSession $session): void
    {
        $this->syncMentions->handle($session, $session->campaign_id, [
            'strong_start' => $session->strong_start,
            'live_notes' => $session->live_notes,
            'recap' => $session->recap,
            'dm_notes' => $session->dm_notes,
        ]);
    }
}
