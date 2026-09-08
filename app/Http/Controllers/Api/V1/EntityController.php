<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EntityType;
use App\Http\Resources\Api\V1\EntityResource;
use App\Models\Campaign;
use App\Models\Entity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Entities\Index and Entities\Show, in JSON. Every query here goes through
 * Entity::visibleTo(), and everything hung on an entity goes through its own scope.
 */
class EntityController extends ApiController
{
    public const PER_PAGE = 50;

    public function index(Request $request, Campaign $campaign): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(EntityType::slugs())],
            'tag' => ['nullable', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $type = isset($validated['type']) ? EntityType::fromSlug($validated['type']) : null;
        $search = mb_strtolower(trim((string) ($validated['q'] ?? '')));
        $tag = Str::slug((string) ($validated['tag'] ?? ''));

        $entities = Entity::query()
            ->visibleTo($this->viewer(), $this->role())
            ->with(['tags', 'media', 'objectives'])
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type?->value))
            ->when($search !== '', fn (Builder $query) => $query->whereRaw('lower(name) like ?', ['%'.$search.'%']))
            ->when($tag !== '', fn (Builder $query) => $query->whereHas('tags', fn (Builder $tags) => $tags->where('slug', $tag)))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return EntityResource::collection($entities);
    }

    public function show(Campaign $campaign, string $entityId): EntityResource
    {
        $user = $this->viewer();
        $role = $this->role();

        $entity = Entity::query()
            ->visibleTo($user, $role)
            ->with(['tags', 'media', 'objectives'])
            ->find($entityId);

        // A hidden entity and a missing one look the same, as they do on the page.
        abort_if($entity === null, 404);

        // Each neighbour through its own gate. A parent the viewer may not see is a
        // null, because "hidden" would tell them there is one.
        $entity->setRelation('parent', $entity->parent_id === null
            ? null
            : Entity::query()->visibleTo($user, $role)->find($entity->parent_id));
        $entity->setRelation('children', $entity->children()->visibleTo($user, $role)->orderBy('name')->get());
        $entity->setRelation('relations', $entity->relations()->visibleTo($user, $role)->with('target')->get());
        $entity->setRelation('incomingRelations', $entity->incomingRelations()->visibleTo($user, $role)->with('source')->orderBy('created_at')->get());

        if ($entity->isQuest()) {
            $entity->setRelation('giver', $entity->giver_entity_id === null
                ? null
                : Entity::query()->visibleTo($user, $role)->find($entity->giver_entity_id));
        }

        return new EntityResource($entity);
    }
}
