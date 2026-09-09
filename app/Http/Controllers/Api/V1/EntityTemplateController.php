<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Entities\CreateEntityTemplate;
use App\Actions\Entities\UpdateEntityTemplate;
use App\Enums\EntityType;
use App\Http\Resources\Api\V1\EntityTemplateResource;
use App\Models\Campaign;
use App\Models\EntityTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EntityTemplateController extends ApiController
{
    public function index(Request $request, Campaign $campaign): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [EntityTemplate::class, $campaign]);
        $validated = $request->validate(['type' => ['nullable', Rule::enum(EntityType::class)]]);
        $templates = EntityTemplate::query()
            ->when(isset($validated['type']), fn ($query) => $query->where('type', $validated['type']))
            ->orderBy('type')->orderBy('name')->orderBy('id')
            ->paginate(50, ['id', 'type', 'name', 'created_at', 'updated_at'])->withQueryString();

        return EntityTemplateResource::collection($templates);
    }

    public function show(Campaign $campaign, string $templateId): EntityTemplateResource
    {
        Gate::authorize('viewAny', [EntityTemplate::class, $campaign]);
        $template = EntityTemplate::query()->findOrFail($templateId);
        Gate::authorize('view', $template);

        return new EntityTemplateResource($template);
    }

    public function store(Request $request, Campaign $campaign, CreateEntityTemplate $create): JsonResponse
    {
        Gate::authorize('create', [EntityTemplate::class, $campaign]);
        $validated = $request->validate($this->rules($request, false));
        $template = $create->handle($campaign, EntityType::from($validated['type']), $validated['name'], $validated['body'] ?? null);

        return (new EntityTemplateResource($template))->response()->setStatusCode(201);
    }

    public function update(Request $request, Campaign $campaign, string $templateId, UpdateEntityTemplate $update): EntityTemplateResource
    {
        Gate::authorize('viewAny', [EntityTemplate::class, $campaign]);
        $template = EntityTemplate::query()->findOrFail($templateId);
        Gate::authorize('update', $template);
        $validated = $request->validate($this->rules($request, true));

        return new EntityTemplateResource($update->handle($template, $validated));
    }

    /** @return array<string, list<mixed>> */
    private function rules(Request $request, bool $patch): array
    {
        $rules = [
            'name' => [$patch ? 'sometimes' : 'required', 'required', 'string', 'max:120'],
            'type' => [$patch ? 'sometimes' : 'required', 'required', Rule::enum(EntityType::class)],
            'body' => ['nullable', 'string', 'max:100000'],
        ];

        foreach (array_keys($request->all()) as $key) {
            if (! array_key_exists($key, $rules)) {
                $rules[$key] = ['prohibited'];
            }
        }

        return $rules;
    }
}
