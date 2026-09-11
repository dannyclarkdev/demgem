<?php

namespace App\Actions\Campaigns;

use App\Actions\Clocks\Segments;
use App\Actions\Maps\Coordinate;
use App\Enums\EncounterStatus;
use App\Enums\EntityType;
use App\Enums\LedgerKind;
use App\Enums\PrepRole;
use App\Enums\QuestStatus;
use App\Enums\Ruleset;
use App\Enums\SessionStatus;
use App\Enums\Visibility;
use App\Models\Calendar;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Decision;
use App\Models\Encounter;
use App\Models\EntityRelation;
use App\Models\GameSession;
use App\Models\LedgerEntry;
use App\Models\StatBlock;
use App\Support\Reckoning\Bounds;
use App\Support\Reckoning\GameDate;
use App\Support\Reckoning\Reckoning;
use BackedEnum;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use JsonException;

/**
 * Reads a campaign file and decides whether this install can build from it.
 *
 * It never touches the database. That is what makes the confirm screen honest — the
 * GM is shown a report of a file nothing has written yet — and it is why most of the
 * interesting tests here need no campaign at all.
 *
 * A file is a claim, not a fact. Every enum goes through tryFrom, every reference has
 * to resolve inside the file itself, and every string is cut to the column it is
 * going into. The one lenient rule is that truncation: a name too long for its column
 * comes from a hand-edited file or a later version, and losing four words is better
 * than losing the campaign. Everything else refuses the file whole and says why.
 */
class ReadCampaignFile
{
    public const FORMAT = ExportCampaign::FORMAT;

    public const VERSION = ExportCampaign::VERSION;

    /**
     * json_decode holds the whole document, and a PHP array of it costs several times
     * the bytes on disk. 25MB is far past any real campaign — the demo seed is 40KB —
     * and the refusal says the number rather than dying on memory.
     */
    public const MAX_BYTES = 26_214_400;

    /** @var list<string> */
    private array $errors = [];

    private ImportReport $report;

    /** @var array<string, true> */
    private array $entityIds = [];

    /** @var array<string, true> */
    private array $sessionIds = [];

    /** @var array<string, true> */
    private array $tableIds = [];

    /** @var array<string, true> */
    private array $combatantIds = [];

    /** @var array<string, true> */
    private array $slugs = [];

    /** @var array<string, true> */
    private array $statBlockIds = [];

    public function handle(string $json): ReadResult
    {
        $this->errors = [];
        $this->report = new ImportReport;
        $this->entityIds = $this->sessionIds = $this->tableIds = $this->combatantIds = $this->slugs = [];
        $this->statBlockIds = [];

        if (strlen($json) > self::MAX_BYTES) {
            return ReadResult::failed([
                'That file is larger than '.round(self::MAX_BYTES / 1_048_576).'MB, which is more than an import reads in one piece. Both the browser and artisan importer have this limit.',
            ]);
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return ReadResult::failed(['That file is not valid JSON: '.$e->getMessage()]);
        }

        if (! is_array($decoded)) {
            return ReadResult::failed(['That file does not hold a campaign document.']);
        }

        if (($decoded['format'] ?? null) !== self::FORMAT) {
            return ReadResult::failed([
                'That is not a demgem campaign export. The format says "'.$this->describe($decoded['format'] ?? null).'" rather than "'.self::FORMAT.'".',
            ]);
        }

        if (($decoded['version'] ?? null) !== self::VERSION) {
            return ReadResult::failed([
                'That file is version '.$this->describe($decoded['version'] ?? null).' and this demgem reads version '.self::VERSION.'. A newer file needs a newer demgem.',
            ]);
        }

        // Read before the sections that point at it, so a reference to a creature this
        // campaign wrote can be checked against the rows the file actually carries.
        $statBlocks = $this->statBlocks($this->list($decoded, 'stat_blocks'));

        $document = [
            'campaign' => $this->campaign($this->rows($decoded, 'campaign')),
            'stat_blocks' => $statBlocks,
            'entities' => $this->entities($this->list($decoded, 'entities')),
            'entity_templates' => $this->entityTemplates($decoded),
            'entity_body_revisions' => $this->entityBodyRevisions($decoded),
            'sessions' => $this->sessions($this->list($decoded, 'sessions')),
            'encounters' => $this->encounters($this->list($decoded, 'encounters')),
            'random_tables' => $this->randomTables($this->list($decoded, 'random_tables')),
            'clocks' => $this->clocks($this->list($decoded, 'clocks')),
            'decisions' => $this->decisions($this->list($decoded, 'decisions')),
            'ledger' => $this->ledger($this->list($decoded, 'ledger')),
        ];

        $this->countTheUncarried($decoded);
        $this->checkReferences($document);

        return $this->errors === []
            ? ReadResult::ok($document, $this->report)
            : ReadResult::failed($this->errors);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function campaign(array $row): array
    {
        return [
            'name' => $this->text($row, 'name', 120, 'the campaign name') ?? 'Imported campaign',
            'description' => $this->text($row, 'description', 2000),
            'ruleset' => $this->enum(Ruleset::class, $row, 'ruleset', 'the campaign') ?? Ruleset::cases()[0],
            'timezone' => $this->text($row, 'timezone', 64) ?? 'UTC',
            'session_length_minutes' => min(720, max(30, $this->integer($row, 'session_length_minutes') ?? 240)),
            'reminder_lead_hours' => $this->reminderLead($row),
            'currency' => $this->text($row, 'currency', Campaign::MAX_CURRENCY_LENGTH) ?? 'gp',
            'cover' => $this->mediaReference($row['cover'] ?? null),
            'calendar' => $this->calendar($row['calendar'] ?? null),
        ];
    }

    /**
     * A day in the world, or null. Checked against the bounds only, never against the
     * calendar: the calendar may itself have been dropped, and a date the months
     * cannot place still prints as "4 month 13, 1042". A date the bounds refuse is
     * dropped and counted.
     *
     * @param  array<string, mixed>  $row
     */
    private function gameDate(array $row, string $key): ?GameDate
    {
        $value = $row[$key] ?? null;

        if ($value === null) {
            return null;
        }

        $date = is_array($value) ? new GameDate(
            $this->integer($value, 'year') ?? 0,
            $this->integer($value, 'month') ?? 0,
            $this->integer($value, 'day') ?? 0,
        ) : null;

        if ($date === null
            || $date->year < Bounds::MIN_YEAR || $date->year > Bounds::MAX_YEAR
            || $date->month < 1 || $date->month > Bounds::MAX_MONTHS
            || $date->day < 1 || $date->day > Bounds::MAX_DAYS) {
            $this->report->truncated++;

            return null;
        }

        return $date;
    }

    /**
     * The world's calendar, or null. Every number goes through Bounds, and a calendar
     * whose shape does not hold, no months, a date the months cannot place, is
     * dropped and counted rather than fatal: the campaign is the point.
     *
     * @return array{name: string, era: string|null, months: list<array{name: string, days: int}>, weekdays: list<string>, moons: list<array{name: string, cycle: float, offset: int}>, leap_every: int|null, leap_month: int|null, current_year: int, current_month: int, current_day: int}|null
     */
    private function calendar(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            $this->report->truncated++;

            return null;
        }

        $months = [];

        foreach (array_slice($this->list($value, 'months'), 0, Bounds::MAX_MONTHS) as $index => $month) {
            $name = is_array($month) ? $this->text($month, 'name', Bounds::MAX_NAME_LENGTH) : null;
            $days = is_array($month) ? $this->integer($month, 'days') : null;

            if ($name === null || $days === null) {
                continue;
            }

            $months[] = ['name' => $name, 'days' => Bounds::clampDays($days)];
        }

        $moons = [];

        foreach (array_slice($this->list($value, 'moons'), 0, Bounds::MAX_MOONS) as $moon) {
            $name = is_array($moon) ? $this->text($moon, 'name', Bounds::MAX_NAME_LENGTH) : null;
            $cycle = is_array($moon) && is_numeric($moon['cycle'] ?? null) ? Bounds::clampCycle((float) $moon['cycle']) : null;

            if ($name === null || $cycle === null) {
                continue;
            }

            $moons[] = [
                'name' => $name,
                'cycle' => $cycle,
                'offset' => Bounds::clampOffset($this->integer($moon, 'offset') ?? 0, $cycle),
            ];
        }

        $leapEvery = $this->integer($value, 'leap_every');
        $leapMonth = $this->integer($value, 'leap_month');
        $leap = $leapEvery !== null && $leapMonth !== null
            && $leapEvery >= Bounds::MIN_LEAP_EVERY && $leapEvery <= Bounds::MAX_LEAP_EVERY
            && $leapMonth >= 1 && $leapMonth <= count($months);

        $reckoning = new Reckoning(
            months: $months,
            weekdays: array_slice($this->strings($value, 'weekdays', Bounds::MAX_NAME_LENGTH), 0, Bounds::MAX_WEEKDAYS),
            moons: $moons,
            leapEvery: $leap ? $leapEvery : null,
            leapMonth: $leap ? $leapMonth : null,
        );

        $current = $this->rows($value, 'current');
        $today = new GameDate(
            $this->integer($current, 'year') ?? 0,
            $this->integer($current, 'month') ?? 0,
            $this->integer($current, 'day') ?? 0,
        );

        if ($months === [] || ! $reckoning->isValid($today)) {
            $this->report->truncated++;

            return null;
        }

        return [
            'name' => $this->text($value, 'name', Calendar::MAX_NAME_LENGTH) ?? 'The calendar',
            'era' => $this->text($value, 'era', Calendar::MAX_ERA_LENGTH),
            'months' => $reckoning->months,
            'weekdays' => $reckoning->weekdays,
            'moons' => $reckoning->moons,
            'leap_every' => $reckoning->leapEvery,
            'leap_month' => $reckoning->leapMonth,
            'current_year' => $today->year,
            'current_month' => $today->month,
            'current_day' => $today->day,
        ];
    }

    /**
     * Only the values the settings screen offers. Anything else means off, which is
     * the hidden direction for a feature that sends email.
     *
     * @param  array<string, mixed>  $row
     */
    private function reminderLead(array $row): ?int
    {
        $hours = $this->integer($row, 'reminder_lead_hours');

        return $hours !== null && array_key_exists((string) $hours, Campaign::reminderLeadOptions()) ? $hours : null;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    private function entityTemplates(array $decoded): array
    {
        return $this->historySection($decoded, 'entity_templates', [
            '*.type' => ['required', Rule::enum(EntityType::class)],
            '*.name' => ['required', 'string', 'max:120'],
            '*.created_at' => ['nullable', 'date'],
            '*.updated_at' => ['nullable', 'date'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    private function entityBodyRevisions(array $decoded): array
    {
        $rows = $this->historySection($decoded, 'entity_body_revisions', [
            '*.entity_id' => ['required', 'string'],
            '*.replaced_by_name' => ['nullable', 'string', 'max:255'],
            '*.recorded_at' => ['required', 'date'],
        ]);

        foreach ($rows as $row) {
            $this->mustResolve($row['entity_id'], $this->entityIds, 'entity', 'a body revision');
        }

        return $rows;
    }

    /**
     * Historical prose must arrive byte-for-byte, never through text()'s trim and truncation.
     *
     * @param  array<string, mixed>  $decoded
     * @param  array<string, list<mixed>>  $rules
     * @return list<array<string, mixed>>
     */
    private function historySection(array $decoded, string $section, array $rules): array
    {
        $rows = $decoded[$section] ?? [];
        $validator = Validator::make(['rows' => $rows], [
            'rows' => ['array', 'list'],
            'rows.*' => ['array'],
            'rows.*.id' => ['required', 'string', 'distinct'],
            'rows.*.body' => ['present', 'nullable', 'string', 'max:100000'],
            ...collect($rules)->mapWithKeys(fn (array $value, string $key) => ['rows.'.$key => $value])->all(),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->errors[] = $section.': '.$error;
            }

            return [];
        }

        $this->report->count($section, count($rows));

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function entities(array $rows): array
    {
        $entities = [];

        foreach ($rows as $index => $row) {
            $id = $this->id($row, 'entities', $index);

            if ($id === null) {
                continue;
            }

            if (isset($this->entityIds[$id])) {
                $this->errors[] = "Two pages in that file share the id {$id}.";
            }

            $this->entityIds[$id] = true;

            $visibility = $this->enum(Visibility::class, $row, 'visibility', "entity {$id}") ?? Visibility::Dm;

            // Nothing is ever made more visible than the file says. A Selected list
            // names people this install does not know, so it arrives GM-only rather
            // than being widened to the whole party.
            if ($visibility === Visibility::Selected) {
                $visibility = Visibility::Dm;
                $this->report->selectedLists++;
            }

            $image = $this->mediaReference($row['image'] ?? null);
            $files = $this->mediaReferences($this->list($row, 'files'));

            $this->report->files += ($image === null ? 0 : 1) + count($files);

            $entities[] = [
                'id' => $id,
                'type' => $this->enum(EntityType::class, $row, 'type', "entity {$id}") ?? EntityType::Note,
                'name' => $this->text($row, 'name', 120, "entity {$id}") ?? 'Untitled',
                'slug' => $this->slug($row, $id),
                'body' => $this->bodyText($row),
                'dm_notes' => $this->text($row, 'dm_notes', 100_000),
                'rewards' => $this->text($row, 'rewards', 100_000),
                'visibility' => $visibility,
                'parent_id' => $this->reference($row, 'parent_id'),
                'is_pc' => (bool) ($row['is_pc'] ?? false),
                'character_class' => $this->text($row, 'character_class', 60),
                'level' => $this->integer($row, 'level'),
                'sheet_url' => $this->url($row, 'sheet_url'),
                'stat_block' => $this->statBlockReference($row),
                'stat_block_id' => $this->ownStatBlockReference($row),
                'quest_status' => $this->optionalEnum(QuestStatus::class, $row, 'quest_status', "entity {$id}"),
                'giver_entity_id' => $this->reference($row, 'giver_entity_id'),
                'arc_id' => $this->reference($row, 'arc_id'),
                'happens_on' => $this->gameDate($row, 'happens_on'),
                'tags' => $this->strings($row, 'tags', 60),
                'objectives' => $this->objectives($row),
                'markers' => $this->markers($row),
                'relations' => $this->relations($row),
                'image' => $image,
                'files' => $files,
            ];
        }

        $this->report->count('entities', count($entities));

        return $entities;
    }

    /**
     * A slug is unique per campaign, so two pages claiming one is a file the database
     * would refuse half way through. Refusing it here turns a foreign key violation
     * a GM cannot act on into a sentence they can.
     *
     * @param  array<string, mixed>  $row
     */
    private function slug(array $row, string $id): ?string
    {
        $slug = $this->text($row, 'slug', 140);

        if ($slug === null) {
            return null;
        }

        if (isset($this->slugs[$slug])) {
            $this->errors[] = "Two pages in that file share the address \"{$slug}\".";
        }

        $this->slugs[$slug] = true;

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function objectives(array $row): array
    {
        $objectives = [];

        foreach ($this->list($row, 'objectives') as $objective) {
            $objectives[] = [
                'position' => $this->integer($objective, 'position') ?? count($objectives),
                'body' => $this->text($objective, 'body', 200) ?? '',
                'completed_at' => $this->text($objective, 'completed_at', 40),
                'completed_in_session_id' => $this->reference($objective, 'completed_in_session_id'),
            ];
        }

        return $objectives;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function markers(array $row): array
    {
        $markers = [];

        foreach ($this->list($row, 'markers') as $marker) {
            $markers[] = [
                'target_entity_id' => $this->reference($marker, 'target_entity_id'),
                'label' => $this->text($marker, 'label', 120) ?? 'Unnamed',
                'x' => Coordinate::clamp((float) ($marker['x'] ?? 0)),
                'y' => Coordinate::clamp((float) ($marker['y'] ?? 0)),
                'player_visible' => (bool) ($marker['player_visible'] ?? false),
            ];
        }

        return $markers;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array{target_entity_id: string|null, label: string, reverse_label: string|null, player_visible: bool, position: int}>
     */
    private function relations(array $row): array
    {
        $relations = [];

        foreach ($this->list($row, 'relations') as $index => $relation) {
            $relations[] = [
                'target_entity_id' => $this->reference($relation, 'target_entity_id'),
                'label' => $this->text($relation, 'label', EntityRelation::MAX_LABEL_LENGTH) ?? 'related to',
                'reverse_label' => $this->text($relation, 'reverse_label', EntityRelation::MAX_LABEL_LENGTH),
                'player_visible' => (bool) ($relation['player_visible'] ?? false),
                'position' => $this->integer($relation, 'position') ?? $index,
            ];
        }

        return $relations;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sessions(array $rows): array
    {
        $sessions = [];
        $numbers = [];

        foreach ($rows as $index => $row) {
            $id = $this->id($row, 'sessions', $index);

            if ($id === null) {
                continue;
            }

            $this->sessionIds[$id] = true;

            $number = $this->integer($row, 'number') ?? count($sessions) + 1;

            if (isset($numbers[$number])) {
                $this->errors[] = "Two sessions in that file are both numbered {$number}.";
            }

            $numbers[$number] = true;

            $sessions[] = [
                'id' => $id,
                'number' => $number,
                'title' => $this->text($row, 'title', 120),
                'scheduled_at' => $this->text($row, 'scheduled_at', 40),
                'in_game_start' => $this->gameDate($row, 'in_game_start'),
                'in_game_end' => $this->gameDate($row, 'in_game_end'),
                'arc_id' => $this->reference($row, 'arc_id'),
                'xp_awarded' => $this->clamped($row, 'xp_awarded', GameSession::MAX_XP),
                'milestone' => $this->text($row, 'milestone', GameSession::MAX_MILESTONE_LENGTH),
                'reminder_sent_at' => $this->text($row, 'reminder_sent_at', 40),
                'status' => $this->enum(SessionStatus::class, $row, 'status', "session {$number}") ?? SessionStatus::cases()[0],
                'visibility' => $this->sessionVisibility($row, $number),
                'strong_start' => $this->text($row, 'strong_start', 100_000),
                'live_notes' => $this->text($row, 'live_notes', 100_000),
                'recap' => $this->text($row, 'recap', 100_000),
                'recap_published_at' => $this->text($row, 'recap_published_at', 40),
                'dm_notes' => $this->text($row, 'dm_notes', 100_000),
                'scenes' => $this->scenes($row),
                'secrets' => $this->secrets($row),
                'prepped' => $this->prepped($row),
                'date_options' => $this->dateOptions($row, $number),
            ];
        }

        $this->report->count('sessions', count($sessions));

        return $sessions;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function sessionVisibility(array $row, int $number): Visibility
    {
        $visibility = $this->enum(Visibility::class, $row, 'visibility', "session {$number}") ?? Visibility::Dm;

        if ($visibility === Visibility::Selected) {
            $this->report->selectedLists++;

            return Visibility::Dm;
        }

        return $visibility;
    }

    /**
     * The candidate times come across; the votes on them name people and are
     * counted into the same loss as the RSVPs.
     *
     * @param  array<string, mixed>  $row
     * @return list<array{starts_at: string, position: int}>
     */
    private function dateOptions(array $row, int $number): array
    {
        $options = [];

        foreach ($this->list($row, 'date_options') as $index => $option) {
            $startsAt = $this->text($option, 'starts_at', 40);

            if ($startsAt === null) {
                $this->errors[] = "A candidate time on session {$number} has no time.";

                continue;
            }

            $this->report->answers += count($this->list($option, 'votes'));

            $options[] = [
                'starts_at' => $startsAt,
                'position' => $this->integer($option, 'position') ?? $index,
            ];
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function scenes(array $row): array
    {
        $scenes = [];

        foreach ($this->list($row, 'scenes') as $scene) {
            $scenes[] = [
                'position' => $this->integer($scene, 'position') ?? count($scenes),
                'title' => $this->text($scene, 'title', 160) ?? 'Untitled scene',
                'notes' => $this->text($scene, 'notes', 100_000),
            ];
        }

        return $scenes;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function secrets(array $row): array
    {
        $secrets = [];

        foreach ($this->list($row, 'secrets') as $secret) {
            $secrets[] = [
                'position' => $this->integer($secret, 'position') ?? count($secrets),
                'body' => $this->text($secret, 'body', 100_000) ?? '',
                'revealed_at' => $this->text($secret, 'revealed_at', 40),
                'revealed_in_session_id' => $this->reference($secret, 'revealed_in_session_id'),
            ];
        }

        return $secrets;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function prepped(array $row): array
    {
        $prepped = [];

        foreach ($this->list($row, 'prepped') as $entry) {
            $role = $this->enum(PrepRole::class, $entry, 'role', 'a prep bucket');

            if ($role === null) {
                continue;
            }

            $prepped[] = [
                'entity_id' => $this->reference($entry, 'entity_id'),
                'role' => $role,
                'position' => $this->integer($entry, 'position') ?? count($prepped),
            ];
        }

        return $prepped;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function encounters(array $rows): array
    {
        $encounters = [];

        foreach ($rows as $index => $row) {
            $id = $this->id($row, 'encounters', $index);

            if ($id === null) {
                continue;
            }

            $combatants = [];

            foreach ($this->list($row, 'combatants') as $combatant) {
                $combatantId = $this->text($combatant, 'id', 40);

                if ($combatantId !== null) {
                    $this->combatantIds[$combatantId] = true;
                }

                $combatants[] = [
                    'id' => $combatantId,
                    'entity_id' => $this->reference($combatant, 'entity_id'),
                    'stat_block' => $this->statBlockReference($combatant),
                    'stat_block_id' => $this->ownStatBlockReference($combatant),
                    'name' => $this->text($combatant, 'name', 120) ?? 'Unnamed',
                    'initiative' => $this->integer($combatant, 'initiative'),
                    'initiative_bonus' => $this->integer($combatant, 'initiative_bonus'),
                    'hp' => $this->integer($combatant, 'hp'),
                    'max_hp' => $this->integer($combatant, 'max_hp'),
                    'ac' => $this->integer($combatant, 'ac'),
                    'conditions' => $this->strings($combatant, 'conditions', 40),
                    'concentrating_on' => $this->text($combatant, 'concentrating_on', Combatant::MAX_CONCENTRATION_LENGTH),
                    'death_save_successes' => $this->clamped($combatant, 'death_save_successes', Combatant::DEATH_SAVES),
                    'death_save_failures' => $this->clamped($combatant, 'death_save_failures', Combatant::DEATH_SAVES),
                    'legendary_actions_max' => $this->clamped($combatant, 'legendary_actions_max', Combatant::MAX_LEGENDARY_ACTIONS),
                    'legendary_actions_left' => $this->clamped($combatant, 'legendary_actions_left', Combatant::MAX_LEGENDARY_ACTIONS),
                    'position' => $this->integer($combatant, 'position') ?? count($combatants),
                    'player_visible' => (bool) ($combatant['player_visible'] ?? false),
                ];
            }

            $encounters[] = [
                'id' => $id,
                'game_session_id' => $this->reference($row, 'game_session_id'),
                'name' => $this->text($row, 'name', 120) ?? 'Encounter',
                'status' => $this->enum(EncounterStatus::class, $row, 'status', "encounter {$id}") ?? EncounterStatus::cases()[0],
                'round' => $this->integer($row, 'round') ?? 1,
                'lair_action_note' => $this->text($row, 'lair_action_note', Encounter::MAX_LAIR_NOTE_LENGTH),
                'lair_initiative' => $this->integer($row, 'lair_initiative'),
                'active_combatant_id' => $this->reference($row, 'active_combatant_id'),
                'combatants' => $combatants,
            ];
        }

        $this->report->count('encounters', count($encounters));

        return $encounters;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function randomTables(array $rows): array
    {
        $tables = [];

        foreach ($rows as $index => $row) {
            $id = $this->id($row, 'random_tables', $index);

            if ($id === null) {
                continue;
            }

            $this->tableIds[$id] = true;

            $entries = [];

            foreach ($this->list($row, 'entries') as $entry) {
                $entries[] = [
                    'position' => $this->integer($entry, 'position') ?? count($entries),
                    'weight' => max(1, $this->integer($entry, 'weight') ?? 1),
                    'body' => $this->text($entry, 'body', 300) ?? '',
                    'nested_table_id' => $this->reference($entry, 'nested_table_id'),
                ];
            }

            $tables[] = [
                'id' => $id,
                'name' => $this->text($row, 'name', 120) ?? 'Table',
                'description' => $this->text($row, 'description', 240),
                'entries' => $entries,
            ];
        }

        $this->report->count('random_tables', count($tables));

        return $tables;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function clocks(array $rows): array
    {
        $clocks = [];

        foreach ($rows as $index => $row) {
            $id = $this->id($row, 'clocks', $index);

            if ($id === null) {
                continue;
            }

            $segments = Segments::clamp($this->integer($row, 'segments') ?? 6);

            $clocks[] = [
                'id' => $id,
                'entity_id' => $this->reference($row, 'entity_id'),
                'name' => $this->text($row, 'name', 120) ?? 'Clock',
                'segments' => $segments,
                'filled' => Segments::clampFill($this->integer($row, 'filled') ?? 0, $segments),
                'player_visible' => (bool) ($row['player_visible'] ?? false),
                'position' => $this->integer($row, 'position') ?? count($clocks),
            ];
        }

        $this->report->count('clocks', count($clocks));

        return $clocks;
    }

    /**
     * A row that is neither a coin movement with an amount nor an item with a name
     * is a row nothing can show, and it is dropped.
     *
     * @param  list<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function ledger(array $rows): array
    {
        $entries = [];

        foreach ($rows as $index => $row) {
            $id = $this->id($row, 'ledger', $index);

            if ($id === null) {
                continue;
            }

            $kind = $this->enum(LedgerKind::class, $row, 'kind', "ledger row {$id}") ?? LedgerKind::Coin;
            $amount = $this->decimal($row, 'amount');
            $name = $this->text($row, 'item_name', LedgerEntry::MAX_ITEM_NAME_LENGTH);
            $quantity = $this->signedInteger($row, 'quantity');

            if ($kind === LedgerKind::Coin && $amount === null) {
                continue;
            }

            if ($kind === LedgerKind::Item && ($name === null || $quantity === null)) {
                continue;
            }

            $entries[] = [
                'id' => $id,
                'game_session_id' => $this->reference($row, 'game_session_id'),
                'kind' => $kind,
                'amount' => $kind === LedgerKind::Coin ? max(-LedgerEntry::MAX_AMOUNT, min(LedgerEntry::MAX_AMOUNT, round((float) $amount, 2))) : null,
                'item_name' => $kind === LedgerKind::Item ? $name : null,
                'quantity' => $kind === LedgerKind::Item ? max(-LedgerEntry::MAX_QUANTITY, min(LedgerEntry::MAX_QUANTITY, (int) $quantity)) : null,
                'entity_id' => $kind === LedgerKind::Item ? $this->reference($row, 'entity_id') : null,
                'note' => $this->text($row, 'note', LedgerEntry::MAX_NOTE_LENGTH),
                'created_at' => $this->text($row, 'created_at', 40),
            ];
        }

        $this->report->count('ledger', count($entries));

        return $entries;
    }

    /**
     * An integer that may be negative, unlike integer(), which reads counts.
     *
     * @param  array<string, mixed>  $row
     */
    private function signedInteger(array $row, string $key): ?int
    {
        $value = $row[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && preg_match('/^-?\\d+$/', $value) === 1 ? (int) $value : null;
    }

    /**
     * @param  list<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function decisions(array $rows): array
    {
        $decisions = [];

        foreach ($rows as $index => $row) {
            $id = $this->id($row, 'decisions', $index);

            if ($id === null) {
                continue;
            }

            $choice = $this->text($row, 'choice', Decision::MAX_LENGTH);

            // A decision is its choice. A row with none is a row nothing can show.
            if ($choice === null) {
                continue;
            }

            $decisions[] = [
                'id' => $id,
                'game_session_id' => $this->reference($row, 'game_session_id'),
                'choice' => $choice,
                'consequence' => $this->text($row, 'consequence', Decision::MAX_LENGTH),
                'player_visible' => (bool) ($row['player_visible'] ?? false),
                'created_at' => $this->text($row, 'created_at', 40),
            ];
        }

        $this->report->count('decisions', count($decisions));

        return $decisions;
    }

    /**
     * The three sections that are counted and left behind, plus the images already
     * counted while the entities were read.
     *
     * @param  array<string, mixed>  $decoded
     */
    private function countTheUncarried(array $decoded): void
    {
        $this->report->diceRolls = count($this->list($decoded, 'dice_rolls'));

        foreach ($this->list($decoded, 'sessions') as $session) {
            $this->report->answers += count($this->list($session, 'attendance'));
        }

        foreach ($this->list($decoded, 'members') as $member) {
            $name = $this->text($member, 'name', 120);

            if ($name !== null) {
                $this->report->memberNames[] = $name;
            }
        }

        if (is_array($this->rows($decoded, 'campaign')['cover'] ?? null)) {
            $this->report->files++;
        }
    }

    /**
     * Every reference has to resolve inside this same file. A parent_id naming nothing
     * is a broken document, not a null: silently dropping it would import a world with
     * its hierarchy quietly flattened.
     *
     * The person columns are the documented exception. They never resolve, which is
     * why the reader does not read them at all.
     *
     * @param  array<string, mixed>  $document
     */
    private function checkReferences(array $document): void
    {
        /** @var list<array<string, mixed>> $entities */
        $entities = $document['entities'];
        /** @var list<array<string, mixed>> $sessions */
        $sessions = $document['sessions'];
        /** @var list<array<string, mixed>> $encounters */
        $encounters = $document['encounters'];
        /** @var list<array<string, mixed>> $tables */
        $tables = $document['random_tables'];
        /** @var list<array<string, mixed>> $clocks */
        $clocks = $document['clocks'];
        /** @var list<array<string, mixed>> $decisions */
        $decisions = $document['decisions'];
        /** @var list<array<string, mixed>> $ledger */
        $ledger = $document['ledger'];

        // An arc is an entity of one type, so the reference is checked against the
        // arcs the file carries rather than every page in it.
        $arcIds = [];

        foreach ($entities as $entity) {
            if ($entity['type'] === EntityType::Arc) {
                $arcIds[$entity['id']] = true;
            }
        }

        foreach ($entities as $entity) {
            $this->mustResolve($entity['parent_id'], $this->entityIds, 'entity', "the parent of \"{$entity['name']}\"");
            $this->mustResolve($entity['giver_entity_id'], $this->entityIds, 'entity', "the giver of \"{$entity['name']}\"");
            $this->mustResolve($entity['arc_id'], $arcIds, 'arc', "the arc of \"{$entity['name']}\"");

            foreach ($entity['objectives'] as $objective) {
                $this->mustResolve($objective['completed_in_session_id'], $this->sessionIds, 'session', 'a completed objective');
            }

            foreach ($entity['markers'] as $marker) {
                $this->mustResolve($marker['target_entity_id'], $this->entityIds, 'entity', "the pin \"{$marker['label']}\"");
            }

            foreach ($entity['relations'] as $relation) {
                $this->mustResolve($relation['target_entity_id'] ?? '', $this->entityIds, 'entity', "the relationship \"{$relation['label']}\" on \"{$entity['name']}\"");
            }
        }

        foreach ($sessions as $session) {
            $this->mustResolve($session['arc_id'], $arcIds, 'arc', "the arc of session {$session['number']}");

            foreach ($session['secrets'] as $secret) {
                $this->mustResolve($secret['revealed_in_session_id'], $this->sessionIds, 'session', 'a revealed secret');
            }

            foreach ($session['prepped'] as $entry) {
                $this->mustResolve($entry['entity_id'], $this->entityIds, 'entity', 'a prepped page');
            }
        }

        foreach ($encounters as $encounter) {
            $this->mustResolve($encounter['game_session_id'], $this->sessionIds, 'session', "the encounter \"{$encounter['name']}\"");
            $this->mustResolve($encounter['active_combatant_id'], $this->combatantIds, 'combatant', "the active turn in \"{$encounter['name']}\"");

            foreach ($encounter['combatants'] as $combatant) {
                $this->mustResolve($combatant['entity_id'], $this->entityIds, 'entity', "the combatant \"{$combatant['name']}\"");
            }
        }

        foreach ($tables as $table) {
            foreach ($table['entries'] as $entry) {
                $this->mustResolve($entry['nested_table_id'], $this->tableIds, 'table', "a row of \"{$table['name']}\"");
            }
        }

        foreach ($clocks as $clock) {
            $this->mustResolve($clock['entity_id'], $this->entityIds, 'entity', "the clock \"{$clock['name']}\"");
        }

        foreach ($decisions as $decision) {
            $this->mustResolve($decision['game_session_id'], $this->sessionIds, 'session', 'a decision');
        }

        foreach ($ledger as $entry) {
            $this->mustResolve($entry['game_session_id'], $this->sessionIds, 'session', 'a ledger row');
            $this->mustResolve($entry['entity_id'], $this->entityIds, 'entity', 'a ledger row');
        }

        $this->checkCycles($entities, 'parent_id', 'The pages in that file nest inside each other in a loop');
        $this->checkCycles($tables, 'nested_table_id', 'The tables in that file nest inside each other in a loop');
    }

    /**
     * A cycle imports fine and breaks later: Entity::ancestors() stops at twenty levels
     * and a breadcrumb quietly truncates. Refusing it now is the kinder failure.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function checkCycles(array $rows, string $column, string $message): void
    {
        $parents = [];

        foreach ($rows as $row) {
            if ($column === 'nested_table_id') {
                foreach ($row['entries'] as $entry) {
                    if ($entry['nested_table_id'] !== null) {
                        $parents[$row['id']][] = $entry['nested_table_id'];
                    }
                }

                continue;
            }

            if ($row[$column] !== null) {
                $parents[$row['id']][] = $row[$column];
            }
        }

        // A walk from each node, looking only for a return to that same node. Marking
        // every node ever seen would call a diamond a cycle: two tables may nest the
        // same third one, which is fine, and only coming back to where you started is
        // a loop. Every node in a cycle is a start eventually, so this finds them all.
        foreach (array_keys($parents) as $start) {
            $seen = [];
            $queue = $parents[$start];

            while ($queue !== []) {
                $current = array_shift($queue);

                if ($current === $start) {
                    $this->errors[] = $message.'.';

                    return;
                }

                if (isset($seen[$current])) {
                    continue;
                }

                $seen[$current] = true;

                foreach ($parents[$current] ?? [] as $next) {
                    $queue[] = $next;
                }
            }
        }
    }

    /**
     * @param  array<string, true>  $known
     */
    private function mustResolve(?string $id, array $known, string $kind, string $where): void
    {
        if ($id !== null && ! isset($known[$id])) {
            $this->errors[] = 'That file points '.$where.' at a '.$kind.' it does not contain.';
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function id(array $row, string $section, int $index): ?string
    {
        $id = $row['id'] ?? null;

        if (! is_string($id) || $id === '') {
            $this->errors[] = "A row in {$section} (number ".($index + 1).') has no id.';

            return null;
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function reference(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $row */
    private function bodyText(array $row): ?string
    {
        $body = $row['body'] ?? null;

        if ($body === null || $body === '') {
            return null;
        }

        if (! is_string($body) || mb_strlen($body) > 100_000) {
            $this->errors[] = 'A body must be text of at most 100000 characters.';

            return null;
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function text(array $row, string $key, int $max, ?string $required = null): ?string
    {
        $value = $row[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (! is_string($value) || trim($value) === '') {
            if ($required !== null) {
                $this->errors[] = 'That file gives no name for '.$required.'.';
            }

            return null;
        }

        $value = trim($value);

        if (mb_strlen($value) > $max) {
            $this->report->truncated++;

            return mb_substr($value, 0, $max);
        }

        return $value;
    }

    /**
     * A reference to a creature in this install's compendium, or nothing.
     *
     * The file names a ruleset and a slug because a stat block id belongs to the
     * install that wrote the file. Resolving happens here so the report can say, before
     * the GM commits, how many links this install has no dataset for. A reference that
     * does not resolve is dropped rather than fatal: the campaign is the point, and the
     * combatant's own numbers came across with it.
     *
     * @param  array<string, mixed>  $row
     * @return array{ruleset: string, slug: string}|null
     */
    /**
     * The creatures the campaign wrote, read the way an entity is.
     *
     * These are campaign rows, unlike the shipped ones: they carry their prose, their
     * id is remapped like every other id, and nothing about them is checked against
     * what this install happens to have loaded.
     *
     * A row with no id is dropped rather than repaired. Every reference to a creature
     * points at one, and a row nothing can name is a row nothing can use.
     *
     * @param  list<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function statBlocks(array $rows): array
    {
        $statBlocks = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = $this->id($row, 'stat_blocks', $index);

            if ($id === null) {
                continue;
            }

            $this->statBlockIds[$id] = true;

            $statBlocks[] = [
                'id' => $id,
                'slug' => $this->text($row, 'slug', 160) ?? 'creature',
                'source' => $this->text($row, 'source', 64) ?? 'Imported campaign',
                'license' => $this->text($row, 'license', 32) ?? StatBlock::OWN_LICENSE,
                'name' => $this->text($row, 'name', 160) ?? 'Unnamed creature',
                'type_line' => $this->text($row, 'type_line', 160),
                'is_swarm' => (bool) ($row['is_swarm'] ?? false),
                'size' => $this->text($row, 'size', 32),
                'creature_type' => $this->text($row, 'creature_type', 48),
                'subtype' => $this->text($row, 'subtype', 64),
                'alignment' => $this->text($row, 'alignment', 64),
                'ac' => $this->clamped($row, 'ac', 999),
                'initiative_bonus' => $this->integer($row, 'initiative_bonus'),
                'hp' => $this->clamped($row, 'hp', 1_000_000),
                'hit_dice' => $this->text($row, 'hit_dice', 64),
                'speed' => $this->text($row, 'speed', 160),
                'ability_scores' => $this->abilityScores($row),
                'skills' => $this->text($row, 'skills', 255),
                'senses' => $this->text($row, 'senses', 255),
                'languages' => $this->text($row, 'languages', 255),
                'gear' => $this->text($row, 'gear', 255),
                'resistances' => $this->text($row, 'resistances', 255),
                'immunities' => $this->text($row, 'immunities', 255),
                'vulnerabilities' => $this->text($row, 'vulnerabilities', 255),
                'cr' => $this->text($row, 'cr', 16),
                'cr_value' => $this->decimal($row, 'cr_value'),
                'xp' => $this->clamped($row, 'xp', 10_000_000),
                'cr_note' => $this->text($row, 'cr_note', 64),
                'traits' => $this->statBlockSection($row, 'traits'),
                'actions' => $this->statBlockSection($row, 'actions'),
                'bonus_actions' => $this->statBlockSection($row, 'bonus_actions'),
                'reactions' => $this->statBlockSection($row, 'reactions'),
                'legendary_actions' => $this->statBlockSection($row, 'legendary_actions'),
                'legendary_action_uses' => $this->clamped($row, 'legendary_action_uses', Combatant::MAX_LEGENDARY_ACTIONS),
            ];
        }

        $this->report->count('stat_blocks', count($statBlocks));

        return $statBlocks;
    }

    /**
     * The six scores as the page prints them. A key that is not one of the six is
     * dropped, so a file cannot grow the shape the stat block page reads.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, array{score: int, mod: string, save: string}>|null
     */
    private function abilityScores(array $row): ?array
    {
        $scores = $row['ability_scores'] ?? null;

        if (! is_array($scores)) {
            return null;
        }

        $clean = [];

        foreach (['str', 'dex', 'con', 'int', 'wis', 'cha'] as $ability) {
            $entry = $scores[$ability] ?? null;

            if (! is_array($entry)) {
                continue;
            }

            $clean[$ability] = [
                'score' => max(0, min(99, (int) ($entry['score'] ?? 0))),
                'mod' => mb_substr(trim((string) ($entry['mod'] ?? '')), 0, 8),
                'save' => mb_substr(trim((string) ($entry['save'] ?? '')), 0, 8),
            ];
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * One list of named entries: a trait, an action, a reaction.
     *
     * The text is a GM's own Markdown and is not trimmed to a tweet, but it is capped:
     * a file is not a place to accept unbounded prose into a column.
     *
     * @param  array<string, mixed>  $row
     * @return list<array{name: string|null, text: string}>|null
     */
    private function statBlockSection(array $row, string $key): ?array
    {
        $entries = [];

        foreach ($this->list($row, $key) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $text = trim((string) ($entry['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));

            $entries[] = [
                'name' => $name === '' ? null : mb_substr($name, 0, 120),
                'text' => mb_substr($text, 0, 10_000),
            ];

            if (count($entries) >= 40) {
                break;
            }
        }

        return $entries === [] ? null : $entries;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function decimal(array $row, string $key): ?float
    {
        $value = $row[$key] ?? null;

        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))
            ? (float) $value
            : null;
    }

    /**
     * A reference to a creature this campaign wrote, checked against the rows the file
     * carries rather than against this install.
     *
     * A shipped reference is the other case and is resolved by (ruleset, slug) against
     * what is loaded here; see statBlockReference(). This one is a plain campaign id and
     * goes through IdMap like every other, which is rule one of .ai/rules/campaigns.md.
     *
     * @param  array<string, mixed>  $row
     */
    private function ownStatBlockReference(array $row): ?string
    {
        $id = $this->reference($row, 'stat_block_id');

        if ($id === null) {
            return null;
        }

        if (! isset($this->statBlockIds[$id])) {
            $this->errors[] = 'A row points at a creature this file does not carry.';

            return null;
        }

        return $id;
    }

    /**
     * A shipped creature, named by the pair the export writes.
     *
     * @param  array<string, mixed>  $row
     * @return array{ruleset: string, slug: string}|null
     */
    private function statBlockReference(array $row): ?array
    {
        $reference = $row['stat_block'] ?? null;

        if (! is_array($reference)) {
            return null;
        }

        $ruleset = $this->text($reference, 'ruleset', 32);
        $slug = $this->text($reference, 'slug', 160);

        if ($ruleset === null || $slug === null) {
            return null;
        }

        $exists = StatBlock::query()
            ->forRuleset($ruleset)
            ->where('slug', $slug)
            ->exists();

        if (! $exists) {
            $this->report->statBlocks++;

            return null;
        }

        return ['ruleset' => $ruleset, 'slug' => $slug];
    }

    /**
     * The one user-supplied URL this app renders outside the Markdown renderer, so the
     * import holds it to the same rule the form does: http and https only, and nothing
     * else becomes a link the whole party can click.
     *
     * @param  array<string, mixed>  $row
     */
    private function url(array $row, string $key): ?string
    {
        $value = $this->text($row, $key, 2048);

        if ($value === null) {
            return null;
        }

        return Str::startsWith(strtolower($value), ['http://', 'https://']) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function integer(array $row, string $key): ?int
    {
        $value = $row[$key] ?? null;

        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    /**
     * A count from a file, held inside the range the application allows.
     *
     * A death save count of nine or a legendary maximum of four hundred is not a
     * campaign this install can run, and refusing the whole import over it would lose
     * a GM's year of notes to a number nothing reads. It is clamped instead.
     *
     * @param  array<string, mixed>  $row
     */
    private function clamped(array $row, string $key, int $max): ?int
    {
        $value = $this->integer($row, $key);

        return $value === null ? null : max(0, min($max, $value));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function strings(array $row, string $key, int $max): array
    {
        $values = [];

        foreach ($this->list($row, $key) as $value) {
            if (is_string($value) && trim($value) !== '') {
                $values[] = mb_substr(trim($value), 0, $max);
            }
        }

        return $values;
    }

    /**
     * What the document says about one file, and nothing this app will act on until
     * it has measured it.
     *
     * archive_path is kept as an opaque string. It is only ever a key into a map the
     * archive reader builds, never a path: that is the rule the whole of this feature
     * rests on, and it holds because nothing here joins it onto a directory.
     *
     * @return array{archive_path: string|null, file_name: string|null}|null
     */
    private function mediaReference(mixed $media): ?array
    {
        if (! is_array($media)) {
            return null;
        }

        $path = $media['archive_path'] ?? null;
        $name = $media['file_name'] ?? null;

        return [
            'archive_path' => is_string($path) && $path !== '' ? $path : null,
            'file_name' => is_string($name) && $name !== '' ? mb_substr($name, 0, 255) : null,
        ];
    }

    /**
     * @param  list<mixed>  $media
     * @return list<array{archive_path: string|null, file_name: string|null}>
     */
    private function mediaReferences(array $media): array
    {
        $references = [];

        foreach ($media as $one) {
            $reference = $this->mediaReference($one);

            if ($reference !== null) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @param  array<string, mixed>  $row
     * @return TEnum|null
     */
    private function enum(string $enum, array $row, string $key, string $where): ?BackedEnum
    {
        $value = $row[$key] ?? null;

        if (! is_string($value)) {
            $this->errors[] = 'That file gives no '.$key.' for '.$where.'.';

            return null;
        }

        $case = $enum::tryFrom($value);

        if ($case === null) {
            // A default here would be a guess about what a GM meant, and the guess is
            // invisible once the campaign exists.
            $this->errors[] = 'That file gives '.$where.' a '.$key.' of "'.$value.'", which this demgem does not know.';
        }

        return $case;
    }

    /**
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @param  array<string, mixed>  $row
     * @return TEnum|null
     */
    private function optionalEnum(string $enum, array $row, string $key, string $where): ?BackedEnum
    {
        return ($row[$key] ?? null) === null ? null : $this->enum($enum, $row, $key, $where);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function rows(array $source, string $key): array
    {
        $value = $source[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return list<mixed>
     */
    private function list(array $source, string $key): array
    {
        $value = $source[$key] ?? [];

        return is_array($value) ? array_values($value) : [];
    }

    private function describe(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : 'nothing';
    }
}
