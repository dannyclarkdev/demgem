<?php

namespace App\Observers;

use App\Actions\Mentions\SyncMentions;
use App\Models\Mention;
use App\Models\Scene;

class SceneObserver
{
    public function __construct(private readonly SyncMentions $syncMentions) {}

    public function saved(Scene $scene): void
    {
        if ($scene->wasRecentlyCreated || $scene->wasChanged('notes')) {
            $this->syncMentions->handle($scene, $scene->campaign_id, ['notes' => $scene->notes]);
        }
    }

    /**
     * Scenes have no soft delete, so a removed scene must not leave mention rows behind.
     */
    public function deleted(Scene $scene): void
    {
        Mention::withoutGlobalScopes()
            ->where('source_type', $scene->getMorphClass())
            ->where('source_id', $scene->id)
            ->delete();
    }
}
