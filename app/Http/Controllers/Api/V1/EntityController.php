<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Entities\CreateEntity;
use App\Actions\Entities\UpdateEntity;
use App\Enums\EntityType;
use App\Enums\QuestStatus;
use App\Enums\Visibility;
use App\Http\Resources\Api\V1\EntityResource;
use App\Models\Campaign;
use App\Models\Entity;
use App\Rules\UniqueEntityName;
use App\Support\Reckoning\Bounds;
use App\Support\Reckoning\GameDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Entities\Index, Show, and Form, in JSON. Every query here goes through
 * Entity::visibleTo(), everything hung on an entity goes through its own scope, and
 * every write goes through the action the form calls, authorised by the same policy.
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

    /**
     * GM roles only, like the form's create. The type is fixed at birth: nothing
     * changes it afterwards, on the page or here.
     */
    public function store(Request $request, Campaign $campaign, CreateEntity $createEntity): JsonResponse
    {
        Gate::authorize('create', [Entity::class, $campaign]);

        $typeSlug = $request->validate(['type' => ['required', 'string', Rule::in(EntityType::slugs())]])['type'];
        $type = EntityType::fromSlug($typeSlug) ?? abort(422);

        $validated = $request->validate($this->rules($campaign, $type, true, null));

        $entity = $createEntity->handle($campaign, $this->viewer(), [
            'type' => $type,
            'name' => $validated['name'],
            ...$this->attributes($validated),
        ]);

        return $this->show($campaign, $entity->id)->response()->setStatusCode(201);
    }

    /**
     * Whoever the policy lets edit: GM roles on anything, a player on their own PC.
     * The GM-only fields are prohibited for a key that may not set them, rather than
     * dropped, because a script deserves to be told.
     */
    public function update(Request $request, Campaign $campaign, string $entityId, UpdateEntity $updateEntity): EntityResource
    {
        $user = $this->viewer();
        $role = $this->role();

        $entity = Entity::query()->visibleTo($user, $role)->find($entityId);

        abort_if($entity === null, 404);

        Gate::authorize('update', $entity);

        $canEditDmFields = $user->can('updateDmFields', $entity);

        $validated = $request->validate($this->rules($campaign, $entity->type, $canEditDmFields, $entity));

        $updateEntity->handle($entity, $user, $this->attributes($validated));

        return $this->show($campaign, $entity->id);
    }

    /**
     * The form's rules, keyed the way the export names things. A field the type does
     * not carry, or the key may not set, is prohibited: a 422 that names it.
     *
     * @return array<string, list<mixed>>
     */
    private function rules(Campaign $campaign, EntityType $type, bool $canEditDmFields, ?Entity $entity): array
    {
        $isCharacter = $type === EntityType::Character;
        $isQuest = $type === EntityType::Quest;
        $isEvent = $type === EntityType::Event;

        $inCampaign = fn (string $table, string $column) => Rule::exists($table, $column)->where('campaign_id', $campaign->id);
        $anotherEntity = fn () => [
            'nullable', 'string',
            Rule::exists('entities', 'id')->where('campaign_id', $campaign->id)->whereNull('deleted_at'),
            ...($entity === null ? [] : [Rule::notIn([$entity->id])]),
        ];

        $rules = [
            'name' => [$entity === null ? 'required' : 'sometimes', 'required', 'string', 'max:120', new UniqueEntityName($campaign->id, $type, $entity?->id)],
            'body' => ['nullable', 'string', 'max:100000'],
            'tags' => ['array', 'max:50'],
            'tags.*' => ['string', 'max:60'],
            'custom_fields' => ['array', 'max:20'],
            'custom_fields.*.key' => ['nullable', 'string', 'max:40'],
            'custom_fields.*.value' => ['nullable', 'string', 'max:200'],
            'character_class' => $isCharacter ? ['nullable', 'string', 'max:60'] : ['prohibited'],
            'level' => $isCharacter ? ['nullable', 'integer', 'min:1', 'max:100'] : ['prohibited'],
            'sheet_url' => $isCharacter ? ['nullable', 'string', 'max:2048', 'url:http,https'] : ['prohibited'],
            'happens_on' => $isEvent ? ['nullable', 'array:year,month,day'] : ['prohibited'],
            'happens_on.year' => ['required_with:happens_on', 'integer', 'min:'.Bounds::MIN_YEAR, 'max:'.Bounds::MAX_YEAR],
            'happens_on.month' => ['required_with:happens_on', 'integer', 'min:1', 'max:'.Bounds::MAX_MONTHS],
            'happens_on.day' => ['required_with:happens_on', 'integer', 'min:1', 'max:'.Bounds::MAX_DAYS],
        ];

        $dmRules = [
            'dm_notes' => ['nullable', 'string', 'max:100000'],
            'visibility' => ['string', Rule::enum(Visibility::class)],
            'parent_id' => $anotherEntity(),
            'viewer_ids' => ['array'],
            'viewer_ids.*' => ['integer', $inCampaign('campaign_members', 'user_id')],
            'is_pc' => $isCharacter ? ['boolean'] : ['prohibited'],
            'player_user_id' => $isCharacter ? ['nullable', 'integer', $inCampaign('campaign_members', 'user_id')] : ['prohibited'],
            'quest_status' => $isQuest ? ['string', Rule::enum(QuestStatus::class)] : ['prohibited'],
            'giver_entity_id' => $isQuest ? $anotherEntity() : ['prohibited'],
            'rewards' => $isQuest ? ['nullable', 'string', 'max:100000'] : ['prohibited'],
        ];

        if (! $canEditDmFields) {
            $dmRules = array_map(fn () => ['prohibited'], $dmRules);
        }

        return $rules + $dmRules;
    }

    /**
     * Validated input to what CreateEntity and UpdateEntity take. Only keys the request
     * sent come through, so a PATCH changes what it names and nothing else.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        $data = [];

        foreach (['name', 'body', 'dm_notes', 'rewards', 'character_class', 'sheet_url', 'parent_id', 'giver_entity_id'] as $key) {
            if (array_key_exists($key, $validated)) {
                $data[$key] = filled($validated[$key]) ? trim((string) $validated[$key]) : null;
            }
        }

        if (array_key_exists('level', $validated)) {
            $data['level'] = $validated['level'] === null ? null : (int) $validated['level'];
        }

        if (array_key_exists('player_user_id', $validated)) {
            $data['player_user_id'] = $validated['player_user_id'] === null ? null : (int) $validated['player_user_id'];
        }

        if (array_key_exists('is_pc', $validated)) {
            $data['is_pc'] = (bool) $validated['is_pc'];
        }

        if (array_key_exists('visibility', $validated)) {
            $data['visibility'] = Visibility::from($validated['visibility']);
        }

        if (array_key_exists('quest_status', $validated)) {
            $data['quest_status'] = QuestStatus::from($validated['quest_status']);
        }

        if (array_key_exists('tags', $validated)) {
            $data['tags'] = array_values(array_unique(array_filter(
                array_map(fn (string $tag) => trim($tag), $validated['tags']),
            )));
        }

        if (array_key_exists('viewer_ids', $validated)) {
            $data['viewer_ids'] = array_map('intval', $validated['viewer_ids']);
        }

        if (array_key_exists('custom_fields', $validated)) {
            $fields = array_map(fn (array $field) => [
                'key' => trim((string) ($field['key'] ?? '')),
                'value' => trim((string) ($field['value'] ?? '')),
            ], $validated['custom_fields']);

            $data['custom_fields'] = array_values(array_filter($fields, fn (array $field) => $field['key'] !== ''));
        }

        if (array_key_exists('happens_on', $validated)) {
            $on = $validated['happens_on'];
            $data['happens_on'] = $on === null ? null : new GameDate((int) $on['year'], (int) $on['month'], (int) $on['day']);
        }

        return $data;
    }
}
