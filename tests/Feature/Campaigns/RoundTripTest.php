<?php

use App\Actions\Campaigns\BuildCampaignArchive;
use App\Actions\Campaigns\ExportCampaign;
use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignArchive;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use Database\Seeders\DemoCampaignSeeder;

/**
 * The centrepiece. Export a campaign, import it, export the copy, and the two
 * documents must match.
 *
 * It compares documents rather than rows on purpose: that states the promise in the
 * same language the feature does, and it fails the day the export grows a field the
 * importer ignores. The section list comes from ExportCampaign::SECTION_TABLES, so a
 * new section joins the comparison without anybody remembering to add it.
 *
 * What is removed before comparing is exactly the documented losses, and nothing else.
 * Every removal here is a promise this slice made in writing.
 */

/**
 * Ids, clocks and the four losses, out. Then every list sorted, because the database
 * promises no order the export does not ask for and a pin's order is not a fact about
 * the campaign.
 */
function comparable(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    $dropped = [
        // Remapped by design: an imported campaign shares no id with its source.
        'id', 'parent_id', 'giver_entity_id', 'target_entity_id', 'entity_id',
        'game_session_id', 'revealed_in_session_id', 'completed_in_session_id',
        'active_combatant_id', 'nested_table_id',
        // This install's clock, not the campaign's.
        'created_at', 'updated_at', 'generated_at',
        // Loss 1: the files are named, never carried.
        'image', 'files', 'cover',
        // Loss 2: the people cannot be re-linked, so no id that names one survives.
        'player_user_id', 'viewer_user_ids', 'user_id', 'joined_at',
    ];

    $clean = [];

    foreach ($value as $key => $item) {
        if (is_string($key) && in_array($key, $dropped, true)) {
            continue;
        }

        $clean[$key] = comparable($item);
    }

    if (array_is_list($clean)) {
        usort($clean, fn ($a, $b) => json_encode($a) <=> json_encode($b));
    }

    return $clean;
}

/**
 * The source document, read the way the importer will read it. Every change here is a
 * loss this slice promised in writing, and nothing else belongs in this function.
 *
 * @param  array<string, mixed>  $document
 * @return array<string, mixed>
 */
function asImported(array $document, User $importer): array
{
    foreach (['entities', 'sessions'] as $section) {
        foreach ($document[$section] as $index => $row) {
            if (($row['visibility'] ?? null) === 'selected') {
                $document[$section][$index]['visibility'] = 'dm';
            }
        }
    }

    // Loss 4: the dice log stays behind.
    $document['dice_rolls'] = [];

    // Loss 2: one member, the importer, as owner. Stated rather than blanked, because
    // "the members section is empty" would pass while the importer was not a member.
    $document['members'] = [['name' => $importer->name, 'role' => 'owner']];

    return $document;
}

it('exports what it imported, section by section', function () {
    $this->seed(DemoCampaignSeeder::class);

    $source = Campaign::query()->firstOrFail();

    $before = exportedArray($source);

    $read = app(ReadCampaignFile::class)->handle(json_encode($before, JSON_THROW_ON_ERROR));

    expect($read->errors)->toBe([]);

    $importer = User::factory()->create();
    $copy = app(ImportCampaign::class)->handle($read->document, $importer);

    $after = exportedArray($copy);

    $expected = comparable(asImported($before, $importer));
    $actual = comparable($after);

    // Driven by the export's own map, so a section added there and forgotten here
    // fails rather than passing quietly.
    foreach (ExportCampaign::SECTION_TABLES as $section) {
        expect($actual[$section])->toBe($expected[$section], "the {$section} section did not survive the round trip");
    }

    expect($actual['campaign'])->toBe($expected['campaign']);
});

it('carries a campaign through twice without drifting', function () {
    $this->seed(DemoCampaignSeeder::class);

    $source = Campaign::query()->firstOrFail();
    $importer = User::factory()->create();

    $once = app(ImportCampaign::class)->handle(
        app(ReadCampaignFile::class)->handle(json_encode(exportedArray($source), JSON_THROW_ON_ERROR))->document,
        $importer,
    );

    $twice = app(ImportCampaign::class)->handle(
        app(ReadCampaignFile::class)->handle(json_encode(exportedArray($once), JSON_THROW_ON_ERROR))->document,
        $importer,
    );

    // The second trip has nothing left to lose, so it must be lossless.
    expect(comparable(exportedArray($twice)))->toBe(comparable(exportedArray($once)));
});

it('carries the pictures through an archive round trip', function () {
    $source = aCampaignWithPictures();

    $before = exportedArray($source);

    $result = app(ReadCampaignArchive::class)
        ->handle(app(BuildCampaignArchive::class)->handle($source));

    expect($result->succeeded())->toBeTrue();

    $importer = User::factory()->create();
    $copy = app(ImportCampaign::class)->handle($result->read->document, $importer, $result->restored);

    $after = exportedArray($copy);

    // Loss 1 is closed, so the media keys stay in the comparison rather than being
    // stripped out of it. Only the URLs differ, because the files are new rows.
    $mediaShape = fn (array $document) => collect($document['entities'])
        ->map(fn (array $entity) => [
            'name' => $entity['name'],
            'image' => $entity['image']['file_name'] ?? null,
            'files' => collect($entity['files'] ?? [])->pluck('file_name')->all(),
        ])
        ->sortBy('name')
        ->values()
        ->all();

    expect($mediaShape($after))->toBe($mediaShape($before));

    $expected = comparable(asImported($before, $importer));
    $actual = comparable($after);

    foreach (ExportCampaign::SECTION_TABLES as $section) {
        expect($actual[$section])->toBe($expected[$section], "the {$section} section did not survive the archive round trip");
    }
});

it('imports the same file twice into two separate campaigns', function () {
    $source = Campaign::factory()->create(['name' => 'The Drowned Duchy']);
    Entity::factory()->for($source)->count(2)->create();

    $document = app(ReadCampaignFile::class)->handle(json_encode(exportedArray($source), JSON_THROW_ON_ERROR))->document;
    $importer = User::factory()->create();

    $first = app(ImportCampaign::class)->handle($document, $importer);
    $second = app(ImportCampaign::class)->handle($document, $importer);

    // The same document twice is two campaigns, not a collision. This is what always
    // remapping the ids buys, and it is the case that breaks an importer that reuses
    // them.
    expect($first->id)->not->toBe($second->id)
        ->and($first->name)->toBe($second->name)
        ->and(Entity::withoutGlobalScopes()->where('campaign_id', $second->id)->count())->toBe(2);
});
