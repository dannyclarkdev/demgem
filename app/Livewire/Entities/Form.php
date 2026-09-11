<?php

namespace App\Livewire\Entities;

use App\Actions\Entities\ApplyEntityTemplate;
use App\Actions\Entities\CreateEntity;
use App\Actions\Entities\UpdateEntity;
use App\Enums\EntityType;
use App\Enums\QuestStatus;
use App\Enums\Visibility;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Markdown\MarkdownRenderer;
use App\Markdown\Secrets\SecretBlocks;
use App\Markdown\WikiLink\WikiLinkRenderer;
use App\Models\Calendar;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\EntityTemplate;
use App\Models\StatBlock;
use App\Models\User;
use App\Rules\UniqueEntityName;
use App\Support\Reckoning\Bounds;
use App\Support\Reckoning\GameDate;
use App\Support\Storage\CampaignStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public ?string $templateId = null;

    public bool $confirmTemplate = false;

    public string $dm_notes = '';

    public string $visibility = Visibility::Dm->value;

    public string $parent_id = '';

    public bool $is_pc = false;

    public string $player_user_id = '';

    public string $character_class = '';

    public ?int $level = null;

    public string $sheet_url = '';

    public string $quest_status = '';

    /**
     * An event's day in the world, three parts, all blank or all set. Only an event
     * has one, and the fields only show when the campaign has a calendar.
     *
     * @var array{year: int|string, month: int|string, day: int|string}
     */
    public array $happensOn = ['year' => '', 'month' => '', 'day' => ''];

    public string $giver_entity_id = '';

    /**
     * The chapter a quest belongs to. A GM field on a quest only, and it must name an
     * arc: the rule below checks the type as well as the campaign.
     */
    public string $arc_id = '';

    /**
     * What an NPC fights as. A GM field, offered only when the campaign's ruleset has a
     * compendium to name, and a reference: the entity's own page owns none of it.
     */
    public string $stat_block_id = '';

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
            $this->authorize('create', [Entity::class, $campaign, $this->entityType]);
            $this->name = (string) request()->query('name', '');
            $this->quest_status = $this->isQuest() ? QuestStatus::Available->value : '';

            return;
        }

        $entity = Entity::query()->ofType($this->entityType)->where('slug', $slug)->with(['tags', 'viewers'])->first();

        abort_if($entity === null || ! $this->user()->can('view', $entity), 404);

        $this->authorize('update', $entity);

        $this->entity = $entity;
        $this->name = $entity->name;
        // A player editing their own page never receives a :::secret fence: the editor
        // holds the stripped body, and save() puts the stored fences back after it.
        $this->body = ($this->user()->can('viewDmNotes', $entity) ? $entity->body : SecretBlocks::strip($entity->body)) ?? '';
        $this->tags = $entity->tags->pluck('name')->implode(', ');
        $this->custom_fields = $entity->customFields();
        $this->happensOn = $entity->happens_on?->toArray() ?? $this->happensOn;

        // The author of a journal chooses between the party and the GM. It is the one
        // visibility a non-DM may set, and it loads for whoever passed the update check.
        if ($this->isJournal()) {
            $this->visibility = $entity->visibility->value;
        }

        // The character record is not a DM field: a player edits their own PC, so these
        // three load for anybody who passed the update check above.
        if ($this->isCharacter()) {
            $this->character_class = $entity->character_class ?? '';
            $this->level = $entity->level;
            $this->sheet_url = $entity->sheet_url ?? '';
            $this->stat_block_id = $entity->stat_block_id ?? '';
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
                $this->arc_id = $entity->arc_id ?? '';
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

    private function isEvent(): bool
    {
        return $this->entityType === EntityType::Event;
    }

    private function isJournal(): bool
    {
        return $this->entityType === EntityType::Journal;
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
            $this->authorize('create', [Entity::class, $this->campaign, $this->entityType]);
        }

        $canEditDmFields = $this->canEditDmFields();

        // The author's switch: the party, or just them and the GM. Selected stays a
        // decision for the DM card, which an author never sees.
        $authorSetsVisibility = $this->isJournal() && ! $canEditDmFields;

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
        // A date means something on an event only, and the part rules are the loose
        // ones: whether the calendar can place the day is checked after, so the error
        // can name the month.
        $rules += $this->isEvent()
            ? [
                'happensOn' => ['array'],
                'happensOn.year' => ['nullable', 'integer', 'min:'.Bounds::MIN_YEAR, 'max:'.Bounds::MAX_YEAR],
                'happensOn.month' => ['nullable', 'integer', 'min:1', 'max:'.Bounds::MAX_MONTHS],
                'happensOn.day' => ['nullable', 'integer', 'min:1', 'max:'.Bounds::MAX_DAYS],
            ]
            : [
                'happensOn' => [Rule::prohibitedIf(fn (): bool => $this->happensOn !== ['year' => '', 'month' => '', 'day' => ''])],
            ];

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

        if ($authorSetsVisibility) {
            $rules += ['visibility' => ['required', Rule::enum(Visibility::class)->only([Visibility::Dm, Visibility::Players])]];
        }

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

            // Prohibited rather than ignored on a ruleset with no compendium, so a form
            // that should not have offered the field says so instead of writing null.
            $rules += $this->offersStatBlock()
                ? ['stat_block_id' => ['nullable', Rule::exists('stat_blocks', 'id')->where('ruleset', $this->campaign->ruleset->value)]]
                : ['stat_block_id' => ['prohibited']];

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
                    'arc_id' => [
                        'nullable',
                        Rule::exists('entities', 'id')
                            ->where('campaign_id', $this->campaign->id)
                            ->where('type', EntityType::Arc->value)
                            ->whereNull('deleted_at'),
                    ],
                    'rewards' => ['nullable', 'string', 'max:100000'],
                ]
                : [
                    'quest_status' => ['prohibited'],
                    'giver_entity_id' => ['prohibited'],
                    'arc_id' => ['prohibited'],
                    'rewards' => ['prohibited'],
                ];
        }

        $validated = $this->validate($rules);

        $happensOn = $this->isEvent() ? $this->happensOnDate() : null;

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        if ($this->isHandout() && $this->fileCountAfterSave() > Entity::MAX_FILES) {
            $this->addError('files', 'A handout carries at most '.Entity::MAX_FILES.' files. Remove one first.');

            return;
        }

        // The campaign's ceiling, checked before any row is written: a refused upload
        // leaves nothing behind. Conversions are not counted; the originals are.
        $incoming = ($this->image?->getSize() ?? 0) + array_sum(array_map(fn (TemporaryUploadedFile $file) => (int) $file->getSize(), $this->files));

        if ($incoming > 0 && ! CampaignStorage::canStore($this->campaign, $incoming)) {
            $this->addError($this->image !== null ? 'image' : 'files', CampaignStorage::refusal($this->campaign));

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

        $body = $validated['body'] !== null && $validated['body'] !== '' ? $validated['body'] : null;

        if ($isEdit && ! $this->user()->can('viewDmNotes', $this->entity)) {
            $body = SecretBlocks::merge($body, $this->entity->body);
        }

        $data = [
            'name' => $validated['name'],
            'body' => $body,
            'tags' => $this->parseTags($validated['tags'] ?? ''),
            'custom_fields' => $this->parseCustomFields($validated['custom_fields'] ?? []),
        ];

        if ($this->isEvent()) {
            $data['happens_on'] = $happensOn;
        }

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

            if ($this->offersStatBlock()) {
                $data['stat_block_id'] = ($validated['stat_block_id'] ?? '') !== '' ? $validated['stat_block_id'] : null;
            }

            if ($this->isQuest()) {
                $data += [
                    'quest_status' => QuestStatus::from($validated['quest_status']),
                    'giver_entity_id' => ($validated['giver_entity_id'] ?? '') !== '' ? $validated['giver_entity_id'] : null,
                    'arc_id' => ($validated['arc_id'] ?? '') !== '' ? $validated['arc_id'] : null,
                    'rewards' => ($validated['rewards'] ?? '') !== '' ? $validated['rewards'] : null,
                ];
            }
        }

        if ($authorSetsVisibility) {
            $data['visibility'] = Visibility::from($validated['visibility']);
        }

        // A journal's author is set at birth and never moves. The DM card's character
        // fields would write null here on a GM's edit, so they come back out.
        if ($this->isJournal()) {
            unset($data['player_user_id'], $data['is_pc']);

            if (! $isEdit) {
                $data['player_user_id'] = $this->user()->id;
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

    public function useTemplate(ApplyEntityTemplate $applyTemplate, bool $confirmed = false): void
    {
        abort_if($this->entity !== null, 403);
        $this->authorize('create', [Entity::class, $this->campaign]);
        $this->validate(['templateId' => ['required', 'string']]);
        try {
            $templateBody = $applyTemplate->handle($this->campaign, $this->user(), $this->entityType, $this->templateId);
        } catch (ModelNotFoundException|ValidationException) {
            $this->addError('templateId', 'That template is no longer available for this entity type. Your draft has been kept.');
            $this->confirmTemplate = false;

            return;
        }

        if ($this->body !== '' && ! $confirmed) {
            $this->confirmTemplate = true;

            return;
        }

        $this->body = $templateBody ?? '';
        $this->bodyPreview = '';
        $this->confirmTemplate = false;
        $this->resetValidation('templateId');
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

        $arcOptions = $canEditDmFields && $this->isQuest()
            ? Entity::query()->ofType(EntityType::Arc)->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('livewire.entities.form', [
            'arcOptions' => $arcOptions,
            'statBlockOptions' => $this->offersStatBlock()
                ? StatBlock::query()
                    ->forCampaign($this->campaign)
                    ->orderBy('name')
                    ->get(['id', 'name', 'cr'])
                : collect(),
            'templateOptions' => $this->entity === null && $canEditDmFields
                ? EntityTemplate::query()->where('type', $this->entityType->value)->orderBy('name')->orderBy('id')->get(['id', 'name']) : collect(),
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
            'isEvent' => $this->isEvent(),
            'isJournal' => $this->isJournal(),
            'authorVisibilities' => [Visibility::Dm, Visibility::Players],
            'months' => $this->isEvent() ? (Calendar::query()->first()?->reckoning()->months ?? []) : [],
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

    /**
     * Whether the form shows the stat block picker at all.
     *
     * A GM field on a character in a campaign with a compendium to pick from. A faction
     * has nothing to fight as. A system-agnostic campaign has no shipped book but may
     * have written its own creatures, so the gate is the compendium's own rather than
     * the ruleset's.
     */
    private function offersStatBlock(): bool
    {
        return $this->canEditDmFields()
            && $this->entityType === EntityType::Character
            && Gate::allows('viewCompendium', $this->campaign);
    }

    private function canEditDmFields(): bool
    {
        if ($this->entity === null) {
            return $this->role()->isDm();
        }

        return $this->user()->can('updateDmFields', $this->entity);
    }

    /**
     * The event's day, or null when every part is blank. A part missing or a day the
     * month lacks lands its error on the field.
     */
    private function happensOnDate(): ?GameDate
    {
        $parts = $this->happensOn;

        if ($parts['year'] === '' && $parts['month'] === '' && $parts['day'] === '') {
            return null;
        }

        foreach (['year', 'month', 'day'] as $part) {
            if ($parts[$part] === '') {
                $this->addError('happensOn.'.$part, 'Fill in the day, the month, and the year, or leave all three blank.');

                return null;
            }
        }

        $date = new GameDate((int) $parts['year'], (int) $parts['month'], (int) $parts['day']);
        $reckoning = Calendar::query()->first()?->reckoning();

        if ($reckoning === null) {
            $this->addError('happensOn.day', 'This campaign has no calendar to place that day in.');

            return null;
        }

        if (! $reckoning->hasMonth($date->month)) {
            $this->addError('happensOn.month', 'That month is not in this calendar.');

            return null;
        }

        if (! $reckoning->isValid($date)) {
            $this->addError('happensOn.day', $reckoning->monthName($date->month).' has '.$reckoning->daysInMonth($date->month, $date->year).' days that year.');

            return null;
        }

        return $date;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
