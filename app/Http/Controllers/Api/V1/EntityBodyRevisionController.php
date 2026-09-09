<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Entities\RestoreEntityBody;
use App\Http\Resources\Api\V1\EntityBodyRevisionResource;
use App\Http\Resources\Api\V1\EntityResource;
use App\Models\Campaign;
use App\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class EntityBodyRevisionController extends ApiController
{
    public function index(Campaign $campaign, string $entityId): AnonymousResourceCollection
    {
        $entity = $this->entity($entityId);

        return EntityBodyRevisionResource::collection($entity->bodyRevisions()
            ->orderByDesc('recorded_at')->orderByDesc('id')
            ->paginate(25, ['id', 'recorded_at', 'replaced_by_name']));
    }

    public function show(Campaign $campaign, string $entityId, string $revisionId): EntityBodyRevisionResource
    {
        $entity = $this->entity($entityId);

        return new EntityBodyRevisionResource($entity->bodyRevisions()->findOrFail($revisionId));
    }

    public function restore(Request $request, Campaign $campaign, string $entityId, string $revisionId, RestoreEntityBody $restore, EntityController $entities): EntityResource
    {
        $entity = $this->entity($entityId);
        $request->validate(array_fill_keys(array_keys($request->all()), ['prohibited']));
        $entity = $restore->handle($entity, $this->viewer(), $revisionId);

        return $entities->show($campaign, $entity->id);
    }

    private function entity(string $entityId): Entity
    {
        $entity = Entity::query()->visibleTo($this->viewer(), $this->role())->findOrFail($entityId);
        Gate::authorize('viewHistory', $entity);

        return $entity;
    }
}
