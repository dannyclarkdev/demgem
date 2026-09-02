<?php

namespace App\Livewire\Entities;

use App\Actions\Entities\CreateEntity;
use App\Actions\Entities\UpdateEntity;
use App\Enums\EntityType;
use App\Enums\Visibility;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Markdown\MarkdownRenderer;
use App\Markdown\WikiLink\WikiLinkRenderer;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use App\Rules\UniqueEntityName;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Form extends Component
{
    use InteractsWithCampaign, WithFileUploads;

    public ?TemporaryUploadedFile $image = null;

    public bool $removeImage = false;

    public ?Entity $entity = null;

    public EntityType $entityType;

    public string $name = '';

    public string $body = '';

    public string $dm_notes = '';

    public string $visibility = Visibility::Dm->value;

    public string $parent_id = '';

    public bool $is_pc = false;

    public string $player_user_id = '';

    public string $tags = '';

    /** @var list<int> */
    public array $viewer_ids = [];

    public string $bodyPreview = '';

    public string $dmNotesPreview = '';

    public function mount(Campaign $campaign, string $type, ?string $slug = null): void
    {
        $this->enterCampaign($campaign);
        $this->entityType = EntityType::fromSlug($type) ?? abort(404);

        if ($slug === null) {
            $this->authorize('create', [Entity::class, $campaign]);
            $this->name = (string) request()->query('name', '');

            return;
        }

        $entity = Entity::query()->ofType($this->entityType)->where('slug', $slug)->with(['tags', 'viewers'])->first();

        abort_if($entity === null || ! $this->user()->can('view', $entity), 404);

        $this->authorize('update', $entity);

        $this->entity = $entity;
        $this->name = $entity->name;
        $this->body = $entity->body ?? '';
        $this->tags = $entity->tags->pluck('name')->implode(', ');

        // DM-only fields never enter the component state for a player. Public properties ship in the Livewire snapshot.
        if ($this->user()->can('updateDmFields', $entity)) {
            $this->dm_notes = $entity->dm_notes ?? '';
            $this->visibility = $entity->visibility->value;
            $this->parent_id = $entity->parent_id ?? '';
            $this->is_pc = $entity->is_pc;
            $this->player_user_id = $entity->player_user_id !== null ? (string) $entity->player_user_id : '';
            $this->viewer_ids = $entity->viewers->pluck('id')->all();
        }
    }

    public function save(CreateEntity $createEntity, UpdateEntity $updateEntity): void
    {
        $isEdit = $this->entity !== null;

        if ($isEdit) {
            $this->authorize('update', $this->entity);
        } else {
            $this->authorize('create', [Entity::class, $this->campaign]);
        }

        $canEditDmFields = $this->canEditDmFields();

        $rules = [
            'name' => ['required', 'string', 'max:120', new UniqueEntityName($this->campaign->id, $this->entityType, $this->entity?->id)],
            'body' => ['nullable', 'string', 'max:100000'],
            'tags' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];

        if ($canEditDmFields) {
            $rules += [
                'dm_notes' => ['nullable', 'string', 'max:100000'],
                'visibility' => ['required', Rule::enum(Visibility::class)],
                'parent_id' => [
                    'nullable',
                    Rule::exists('entities', 'id')
                        ->where('campaign_id', $this->campaign->id)
                        ->where('type', $this->entityType->value)
                        ->whereNull('deleted_at'),
                ],
                'is_pc' => ['boolean'],
                'player_user_id' => ['nullable', Rule::exists('campaign_members', 'user_id')->where('campaign_id', $this->campaign->id)],
                'viewer_ids' => ['array'],
                'viewer_ids.*' => ['integer', Rule::exists('campaign_members', 'user_id')->where('campaign_id', $this->campaign->id)],
            ];
        }

        $validated = $this->validate($rules);

        if ($canEditDmFields && $isEdit && ($validated['parent_id'] ?? '') === $this->entity->id) {
            $this->addError('parent_id', 'An entity cannot be its own parent.');

            return;
        }

        $data = [
            'name' => $validated['name'],
            'body' => $validated['body'] !== null && $validated['body'] !== '' ? $validated['body'] : null,
            'tags' => $this->parseTags($validated['tags'] ?? ''),
        ];

        if ($canEditDmFields) {
            $isCharacter = $this->entityType === EntityType::Character;
            $visibility = Visibility::from($validated['visibility']);

            $data += [
                'dm_notes' => $validated['dm_notes'] !== null && $validated['dm_notes'] !== '' ? $validated['dm_notes'] : null,
                'visibility' => $visibility,
                'parent_id' => ($validated['parent_id'] ?? '') !== '' ? $validated['parent_id'] : null,
                'is_pc' => $isCharacter && (bool) ($validated['is_pc'] ?? false),
                'player_user_id' => $isCharacter && ($validated['player_user_id'] ?? '') !== '' ? (int) $validated['player_user_id'] : null,
                'viewer_ids' => $visibility === Visibility::Selected ? array_map('intval', $validated['viewer_ids'] ?? []) : [],
            ];
        }

        $entity = $isEdit
            ? $updateEntity->handle($this->entity, $this->user(), $data)
            : $createEntity->handle($this->campaign, $this->user(), [...$data, 'type' => $this->entityType]);

        if ($this->removeImage) {
            $entity->clearMediaCollection('image');
        }

        if ($this->image !== null) {
            $entity->addMedia($this->image->getRealPath())
                ->usingFileName($this->image->getClientOriginalName())
                ->toMediaCollection('image');
        }

        session()->flash('status', $isEdit ? "{$entity->name} saved." : "{$entity->name} created.");

        $this->redirect($entity->url());
    }

    public function previewBody(MarkdownRenderer $renderer): void
    {
        $this->bodyPreview = $renderer->render($this->body, $this->wikiLinkRenderer());
    }

    public function previewDmNotes(MarkdownRenderer $renderer): void
    {
        $this->dmNotesPreview = $this->canEditDmFields() ? $renderer->render($this->dm_notes, $this->wikiLinkRenderer()) : '';
    }

    private function wikiLinkRenderer(): WikiLinkRenderer
    {
        return WikiLinkRenderer::for($this->campaign, $this->user(), $this->role());
    }

    public function render(): View
    {
        $canEditDmFields = $this->canEditDmFields();

        $parentOptions = $canEditDmFields
            ? Entity::query()->ofType($this->entityType)->when($this->entity, fn ($q) => $q->whereKeyNot($this->entity?->id))->orderBy('name')->get(['id', 'name'])
            : collect();

        $members = $canEditDmFields
            ? $this->campaign->members()->with('user')->get()->sortBy(fn ($m) => $m->user->name)->values()
            : collect();

        return view('livewire.entities.form', [
            'type' => $this->entityType,
            'isEdit' => $this->entity !== null,
            'canEditDmFields' => $canEditDmFields,
            'parentOptions' => $parentOptions,
            'memberOptions' => $members,
            'viewerOptions' => $members->filter(fn ($m) => ! $m->role->isDm())->values(),
            'visibilities' => Visibility::cases(),
            'isCharacter' => $this->entityType === EntityType::Character,
            'autocompleteUrl' => route('entities.autocomplete', $this->campaign),
        ])->title(($this->entity !== null ? 'Edit ' : 'New ').strtolower($this->entityType->label()));
    }

    /**
     * @return list<string>
     */
    private function parseTags(string $input): array
    {
        return collect(explode(',', $input))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function canEditDmFields(): bool
    {
        if ($this->entity === null) {
            return $this->role()->isDm();
        }

        return $this->user()->can('updateDmFields', $this->entity);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
