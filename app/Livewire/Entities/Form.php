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
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Form extends Component
{
    use InteractsWithCampaign, WithFileUploads;

    public ?TemporaryUploadedFile $image = null;

    public bool $removeImage = false;

    /**
     * New attachments for a handout, this save only.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $files = [];

    /**
     * Ids of attachments the GM ticked to remove.
     *
     * @var array<int, int>
     */
    public array $removeFileIds = [];

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

    /**
     * The GM's key-value pairs, in typed order. A row with an empty key is the empty
     * row at the bottom of the editor, and it is dropped on save rather than rejected.
     *
     * @var list<array{key: string, value: string}>
     */
    public array $custom_fields = [];

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
        $this->custom_fields = $entity->customFields();

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

    private function isMap(): bool
    {
        return $this->entityType === EntityType::Map;
    }

    private function isHandout(): bool
    {
        return $this->entityType === EntityType::Handout;
    }

    /**
     * The upload cap for one handout attachment, in kilobytes. The same ten megabytes
     * a map image gets, and the same ceiling config/media-library.php sets.
     */
    public const FILE_KB = 10240;

    /**
     * The upload cap for a map image, in kilobytes. Ten megabytes, which is what
     * config/media-library.php allows, and roughly a 6000px PNG export.
     */
    public const MAP_IMAGE_KB = 10240;

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
            // A map is the image rather than a portrait beside the prose, so it gets
            // twice the room: a hand-drawn scan at a readable resolution does not fit
            // in five megabytes.
            //
            // Still optional. A GM writing the world down at midnight should be able
            // to make the row now and find the file tomorrow, and the map page says
            // so rather than refusing to exist.
            'image' => ['nullable', 'image', 'max:'.($this->isMap() ? self::MAP_IMAGE_KB : 5120)],
            'custom_fields' => ['array', 'max:20'],
            'custom_fields.*.key' => ['nullable', 'string', 'max:40'],
            'custom_fields.*.value' => ['nullable', 'string', 'max:200'],
        ];

        // Attachments are a handout's whole point, and they are prohibited elsewhere
        // rather than quietly ignored, the way the quest fields are.
        $rules += $this->isHandout()
            ? [
                'files' => ['array', 'max:'.Entity::MAX_FILES],
                'files.*' => ['file', 'max:'.self::FILE_KB, 'mimes:jpg,jpeg,png,webp,gif,pdf'],
                'removeFileIds' => ['array'],
                'removeFileIds.*' => ['integer'],
            ]
            : [
                'files' => ['prohibited'],
                'removeFileIds' => ['prohibited'],
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

        if ($this->isHandout() && $this->fileCountAfterSave() > Entity::MAX_FILES) {
            $this->addError('files', 'A handout carries at most '.Entity::MAX_FILES.' files. Remove one first.');

            return;
        }

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
            'custom_fields' => $this->parseCustomFields($validated['custom_fields'] ?? []),
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

        if ($this->isHandout()) {
            $this->syncFiles($entity);
        }

        session()->flash('status', $isEdit ? "{$entity->name} saved." : "{$entity->name} created.");

        $this->redirect($entity->url());
    }

    /**
     * Removals first, then additions, so a GM who swaps the tenth file for another
     * one in a single save is not stopped by their own ceiling.
     */
    private function syncFiles(Entity $entity): void
    {
        if ($this->removeFileIds !== []) {
            $entity->media()
                ->where('collection_name', 'files')
                ->whereIn('id', $this->removeFileIds)
                ->get()
                ->each(fn (Media $file) => $file->delete());
        }

        foreach ($this->files as $file) {
            $entity->addMedia($file->getRealPath())
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('files');
        }

        $this->files = [];
        $this->removeFileIds = [];
    }

    private function fileCountAfterSave(): int
    {
        $existing = $this->entity?->files()->count() ?? 0;

        return $existing - count($this->removeFileIds) + count($this->files);
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
            'isMap' => $this->isMap(),
            'isHandout' => $this->isHandout(),
            'existingFiles' => $this->isHandout() ? ($this->entity?->files() ?? collect()) : collect(),
            'maxFiles' => Entity::MAX_FILES,
            'questStatuses' => QuestStatus::cases(),
            'giverOptions' => $giverOptions,
            'autocompleteUrl' => route('entities.autocomplete', $this->campaign),
        ])->title(($this->entity !== null ? 'Edit ' : 'New ').strtolower($this->entityType->label()));
    }

    public function addCustomField(): void
    {
        if (count($this->custom_fields) < 20) {
            $this->custom_fields[] = ['key' => '', 'value' => ''];
        }
    }

    public function removeCustomField(int $index): void
    {
        unset($this->custom_fields[$index]);

        $this->custom_fields = array_values($this->custom_fields);
    }

    /**
     * Drops the empty rows, trims both sides, and strips control characters. These
     * render as plain text in a definition list, so nothing else needs cleaning.
     *
     * @param  array<int, mixed>  $input
     * @return list<array{key: string, value: string}>|null
     */
    private function parseCustomFields(array $input): ?array
    {
        $fields = collect($input)
            ->map(fn (mixed $field): array => [
                'key' => $this->cleanFieldText(is_array($field) ? ($field['key'] ?? '') : ''),
                'value' => $this->cleanFieldText(is_array($field) ? ($field['value'] ?? '') : ''),
            ])
            ->filter(fn (array $field): bool => $field['key'] !== '')
            ->take(20)
            ->values()
            ->all();

        return $fields === [] ? null : $fields;
    }

    private function cleanFieldText(mixed $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $value));
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
