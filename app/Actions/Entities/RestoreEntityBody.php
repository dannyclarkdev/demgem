<?php

namespace App\Actions\Entities;

use App\Models\Entity;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RestoreEntityBody
{
    public function __construct(private readonly UpdateEntity $updateEntity) {}

    public function handle(Entity $entity, User $actor, string $revisionId): Entity
    {
        Gate::forUser($actor)->authorize('restoreBody', $entity);

        $revision = $entity->bodyRevisions()->where('campaign_id', $entity->campaign_id)->findOrFail($revisionId);

        return $this->updateEntity->handle($entity, $actor, ['body' => $revision->body]);
    }
}
