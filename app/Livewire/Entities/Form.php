<?php

namespace App\Livewire\Entities;

use App\Actions\Entities\CreateEntity;
use App\Actions\Entities\UpdateEntity;
use App\Enums\EntityType;
use App\Enums\QuestStatus;
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

    public string $character_class = '';

    public ?int $level = null;

    public string $sheet_url = '';

    public string $quest_status = '';

    public string $giver_entity_id = '';

    public string $rewards = '';

    public string $tags = '';

    /** @var list<int> */
    public array $viewer_ids = [];

    public string $bodyPreview = '';

    public string $dmNotesPreview = '';

    public string $rewardsPreview = '';

    public function mount(Campaign $campaign, string $type, ?string $slug = null): void
    {
        $this->enterCampaign($campaign);
        $this->entityType = EntityType::fromSlug($type) ?? abort(404);

        if ($slug === null) {
            $this->authorize('create', [Entity::class, $campaign]);
            $this->name = (string) request()->query('name', '');
            $this->quest_status = $this->isQuest() ? QuestStatus::Available->value : '';

            return;
        }

        $entity = Entity::query()->ofType($this->entityType)->where('slug', $slug)->with(['tags', 'viewers'])->first();

        abort_if($entity === null || ! $this->user()->can('view', $entity), 404);

        $this->authorize('update', $entity);

        $this->entity = $entity;
        $this->name = $entity->name;
        $this->body = $entity->body ?? '';
        $this->tags = $entity->tags->pluck('name')->implode(', ');

        // The character record is not a DM field: a player edits their own PC, so these
        // three load for anybody who passed the update check above.
        if ($this->isCharacter()) {
            $this->character_class = $entity->character_class ?? '';
            $this->level = $entity->level;
            $this->sheet_url = $entity->sheet_url ?? '';
        }

        // DM-only fields never enter the component state for a player. Public properties ship in the Livewire snapshot.
        if ($this->user()->can('updateDmFields', $entity)) {
            $this->dm_notes = $entity->dm_notes ?? '';
            $this->visibility = $entity->visibility->value;
            $this->parent_id = $entity->parent_id ?? '';
            $this->is_pc = $entity->is_pc;
            $this->player_user_id = $entity->player_user_id !== null ? (string) $entity->player_user_id : '';
            $this->viewer_ids = $entity->viewers->pluck('id')->all();

            if ($this->isQuest()) {
                $this->quest_status = ($entity->questStatus() ?? QuestStatus::Available)->value;
                $this->giver_entity_id = $entity->giver_entity_id ?? '';
                $this->rewards = $entity->rewards ?? '';
            }
        }
    }

    private function isQuest(): bool
    {
        return $this->entityType === EntityType::Quest;
    }

    private function isCharacter(): bool
    {
        return $this->entityType === EntityType::Character;
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

        // The character record is not a DM field, so these rules sit outside the block
        // below: whoever passed the update check may set them, which includes a player
        // on their own PC. sheet_url is the one user URL in the app rendered as an href
        // outside MarkdownRenderer, and url:http,https is what stops javascript: from
        // becoming a link the whole party can click.
        $rules += $this->isCharacter()
            ? [
                'character_class' => ['nullable', 'string', 'max:60'],
                'level' => ['nullable', 'integer', 'min:1', 'max:100'],
                'sheet_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            ]
            : [
                'character_class' => ['prohibited'],
                'level' => ['prohibited'],
                'sheet_url' => ['prohibited'],
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

            // Quest fields exist on every entity row but mean something on one type only,
            // so they are prohibited elsewhere rather than quietly ignored.
            $rules += $this->isQuest()
                ? [
                    'quest_status' => ['required', Rule::enum(QuestStatus::class)],
                    'giver_entity_id' => [
                        'nullable',
                        Rule::exists('entities', 'id')
                            ->where('campaign_id', $this->campaign->id)
                            ->whereNull('deleted_at'),
                    ],
                    'rewards' => ['nullable', 'string', 'max:100000'],
                ]
                : [
                    'quest_status' => ['prohibited'],
                    'giver_entity_id' => ['prohibited'],
                    'rewards' => ['prohibited'],
                ];
        }

        $validated = $this->validate($rules);

        if ($canEditDmFields && $isEdit && ($validated['parent_id'] ?? '') === $this->entity->id) {
            $this->addError('parent_id', 'An entity cannot be its own parent.');

            return;
        }

        if ($canEditDmFields && $isEdit && ($validated['giver_entity_id'] ?? '') === $this->entity->id) {
            $this->addError('giver_entity_id', 'A quest cannot give itself.');

            return;
        }

        $data = [
            'name' => $validated['name'],
            'body' => $validated['body'] !== null && $validated['body'] !== '' ? $validated['body'] : null,
            'tags' => $this->parseTags($validated['tags'] ?? ''),
        ];

        if ($this->isCharacter()) {
            $data += [
                'character_class' => filled($validated['character_class'] ?? null) ? trim((string) $validated['character_class']) : null,
                'level' => $validated['level'] ?? null,
                'sheet_url' => filled($validated['sheet_url'] ?? null) ? trim((string) $validated['sheet_url']) : null,
            ];
        }

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

            if ($this->isQuest()) {
                $data += [
                    'quest_status' => QuestStatus::from($validated['quest_status']),
                    'giver_entity_id' => ($validated['giver_entity_id'] ?? '') !== '' ? $validated['giver_entity_id'] : null,
                    'rewards' => ($validated['rewards'] ?? '') !== '' ? $validated['rewards'] : null,
                ];
            }
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

    public function previewRewards(MarkdownRenderer $renderer): void
    {
        $this->rewardsPreview = $this->canEditDmFields() ? $renderer->render($this->rewards, $this->wikiLinkRenderer()) : '';
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

        // Any entity may give a quest; characters and factions come first because they
        // almost always are the giver.
        $giverOptions = $canEditDmFields && $this->isQuest()
            ? Entity::query()
                ->when($this->entity, fn ($q) => $q->whereKeyNot($this->entity?->id))
                ->orderByRaw("case when type in ('character', 'faction') then 0 else 1 end")
                ->orderBy('name')
                ->get(['id', 'name', 'type'])
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
            'isQuest' => $this->isQuest(),
            'questStatuses' => QuestStatus::cases(),
            'giverOptions' => $giverOptions,
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
