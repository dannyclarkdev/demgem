<?php

namespace App\Actions\Campaigns;

use App\Actions\Clocks\Segments;
use App\Actions\Maps\Coordinate;
use App\Enums\EncounterStatus;
use App\Enums\EntityType;
use App\Enums\PrepRole;
use App\Enums\QuestStatus;
use App\Enums\Ruleset;
use App\Enums\SessionStatus;
use App\Enums\Visibility;
use BackedEnum;
use Illuminate\Support\Str;
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

    public function handle(string $json): ReadResult
    {
        $this->errors = [];
        $this->report = new ImportReport;
        $this->entityIds = $this->sessionIds = $this->tableIds = $this->combatantIds = $this->slugs = [];

        if (strlen($json) > self::MAX_BYTES) {
            return ReadResult::failed([
                'That file is larger than '.round(self::MAX_BYTES / 1_048_576).'MB, which is more than an import reads in one piece. Use the artisan command for a file this size.',
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

        $document = [
            'campaign' => $this->campaign($this->rows($decoded, 'campaign')),
            'entities' => $this->entities($this->list($decoded, 'entities')),
            'sessions' => $this->sessions($this->list($decoded, 'sessions')),
            'encounters' => $this->encounters($this->list($decoded, 'encounters')),
            'random_tables' => $this->randomTables($this->list($decoded, 'random_tables')),
            'clocks' => $this->clocks($this->list($decoded, 'clocks')),
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
        ];
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

            $this->report->files += $this->fileCount($row);

            $entities[] = [
                'id' => $id,
                'type' => $this->enum(EntityType::class, $row, 'type', "entity {$id}") ?? EntityType::Note,
                'name' => $this->text($row, 'name', 120, "entity {$id}") ?? 'Untitled',
                'slug' => $this->slug($row, $id),
                'body' => $this->text($row, 'body', 100_000),
                'dm_notes' => $this->text($row, 'dm_notes', 100_000),
                'rewards' => $this->text($row, 'rewards', 100_000),
                'visibility' => $visibility,
                'parent_id' => $this->reference($row, 'parent_id'),
                'is_pc' => (bool) ($row['is_pc'] ?? false),
                'character_class' => $this->text($row, 'character_class', 60),
                'level' => $this->integer($row, 'level'),
                'sheet_url' => $this->url($row, 'sheet_url'),
                'quest_status' => $this->optionalEnum(QuestStatus::class, $row, 'quest_status', "entity {$id}"),
                'giver_entity_id' => $this->reference($row, 'giver_entity_id'),
                'tags' => $this->strings($row, 'tags', 60),
                'objectives' => $this->objectives($row),
                'markers' => $this->markers($row),
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
                    'name' => $this->text($combatant, 'name', 120) ?? 'Unnamed',
                    'initiative' => $this->integer($combatant, 'initiative'),
                    'initiative_bonus' => $this->integer($combatant, 'initiative_bonus'),
                    'hp' => $this->integer($combatant, 'hp'),
                    'max_hp' => $this->integer($combatant, 'max_hp'),
                    'ac' => $this->integer($combatant, 'ac'),
                    'conditions' => $this->strings($combatant, 'conditions', 40),
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
     * The three sections that are counted and left behind, plus the images already
     * counted while the entities were read.
     *
     * @param  array<string, mixed>  $decoded
     */
    private function countTheUncarried(array $decoded): void
    {
        $this->report->diceRolls = count($this->list($decoded, 'dice_rolls'));

        foreach ($this->list($decoded, 'members') as $member) {
            $name = $this->text($member, 'name', 120);

            if ($name !== null) {
                $this->report->memberNames[] = $name;
            }
        }

        $cover = $this->rows($decoded, 'campaign')['cover'] ?? null;

        if (is_array($cover)) {
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

        foreach ($entities as $entity) {
            $this->mustResolve($entity['parent_id'], $this->entityIds, 'entity', "the parent of \"{$entity['name']}\"");
            $this->mustResolve($entity['giver_entity_id'], $this->entityIds, 'entity', "the giver of \"{$entity['name']}\"");

            foreach ($entity['objectives'] as $objective) {
                $this->mustResolve($objective['completed_in_session_id'], $this->sessionIds, 'session', 'a completed objective');
            }

            foreach ($entity['markers'] as $marker) {
                $this->mustResolve($marker['target_entity_id'], $this->entityIds, 'entity', "the pin \"{$marker['label']}\"");
            }
        }

        foreach ($sessions as $session) {
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
     * @param  array<string, mixed>  $row
     */
    private function fileCount(array $row): int
    {
        $count = is_array($row['image'] ?? null) ? 1 : 0;

        foreach ($this->list($row, 'files') as $file) {
            if (is_array($file)) {
                $count++;
            }
        }

        return $count;
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
