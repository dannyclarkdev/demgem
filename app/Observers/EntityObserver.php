<?php

namespace App\Observers;

use App\Actions\Mentions\ResolveMentionsFor;
use App\Actions\Mentions\RewriteWikiLinks;
use App\Actions\Mentions\SyncMentions;
use App\Models\Entity;
use App\Models\Mention;

class EntityObserver
{
    public function __construct(
        private readonly SyncMentions $syncMentions,
        private readonly ResolveMentionsFor $resolveMentionsFor,
        private readonly RewriteWikiLinks $rewriteWikiLinks,
    ) {}

    public function created(Entity $entity): void
    {
        $this->resolveMentionsFor->handle($entity);
    }

    public function updated(Entity $entity): void
    {
        if ($entity->wasChanged('name')) {
            $this->rewriteWikiLinks->handle($entity, (string) $entity->getOriginal('name'));
            $this->resolveMentionsFor->handle($entity);
        }
    }

    public function saved(Entity $entity): void
    {
        if ($entity->wasRecentlyCreated || $entity->wasChanged(['body', 'dm_notes'])) {
            $this->sync($entity);
        }
    }

    /**
     * Soft delete. Links to it become unresolved again; a later restore or a new entity picks them up.
     */
    public function deleted(Entity $entity): void
    {
        Mention::withoutGlobalScopes()
            ->where('target_entity_id', $entity->id)
            ->update(['target_entity_id' => null]);
    }

    public function restored(Entity $entity): void
    {
        $this->resolveMentionsFor->handle($entity);
        $this->sync($entity);
    }

    private function sync(Entity $entity): void
    {
        $this->syncMentions->handle($entity, $entity->campaign_id, [
            'body' => $entity->body,
            'dm_notes' => $entity->dm_notes,
        ]);
    }
}
