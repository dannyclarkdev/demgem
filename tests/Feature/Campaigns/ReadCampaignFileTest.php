<?php

use App\Actions\Campaigns\ExportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Actions\Campaigns\ReadResult;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\Entity;

/**
 * The reader decides whether this install can build from a file, and it does it
 * without touching the database. That is what makes the confirm screen honest, and it
 * is why almost every test here builds its document by hand.
 */
function aDocument(array $overrides = []): array
{
    return [
        'format' => ExportCampaign::FORMAT,
        'version' => ExportCampaign::VERSION,
        'campaign' => ['name' => 'The Drowned Duchy', 'ruleset' => 'srd-5e-2024', 'timezone' => 'UTC'],
        'members' => [],
        'entities' => [],
        'sessions' => [],
        'encounters' => [],
        'random_tables' => [],
        'dice_rolls' => [],
        'clocks' => [],
        ...$overrides,
    ];
}

function anEntity(string $id, array $overrides = []): array
{
    return [
        'id' => $id,
        'type' => 'note',
        'name' => 'A page named '.$id,
        'slug' => 'page-'.$id,
        'visibility' => 'dm',
        ...$overrides,
    ];
}

function read(array $document): ReadResult
{
    return app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));
}

it('reads a whole exported campaign', function () {
    $campaign = Campaign::factory()->create(['name' => 'The Drowned Duchy']);
    Entity::factory()->for($campaign)->count(3)->create();

    $export = app(ExportCampaign::class)->handle($campaign);

    // The export streams, so the sections are lazy collections until they are read.
    $result = read(json_decode(json_encode(array_map(
        fn ($section) => is_iterable($section) && ! is_array($section) ? iterator_to_array($section) : $section,
        $export,
    ), JSON_THROW_ON_ERROR), true));

    expect($result->succeeded())->toBeTrue()
        ->and($result->document['campaign']['name'])->toBe('The Drowned Duchy')
        ->and($result->report->counts['entities'])->toBe(3);
});

it('refuses a file that is not a demgem export', function () {
    $result = read(aDocument(['format' => 'obsidian.vault']));

    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('not a demgem campaign export')
        ->and($result->errors[0])->toContain('obsidian.vault');
});

it('refuses a version it does not read', function () {
    $result = read(aDocument(['version' => 2]));

    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('version 2')
        ->and($result->errors[0])->toContain('needs a newer demgem');
});

it('refuses something that is not JSON at all', function () {
    $result = app(ReadCampaignFile::class)->handle('not a document');

    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('not valid JSON');
});

it('refuses a file past the size it reads in one piece', function () {
    $result = app(ReadCampaignFile::class)->handle(str_repeat('x', ReadCampaignFile::MAX_BYTES + 1));

    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('25MB')
        ->and($result->errors[0])->toContain('artisan command');
});

it('refuses an enum value it does not know rather than guessing one', function (string $key, string $value, string $needle) {
    $result = read(aDocument(['entities' => [anEntity('e1', [$key => $value])]]));

    expect($result->succeeded())->toBeFalse()
        ->and(implode(' ', $result->errors))->toContain($needle);
})->with([
    'type' => ['type', 'spaceship', 'spaceship'],
    'visibility' => ['visibility', 'everyone-ish', 'everyone-ish'],
    'quest status' => ['quest_status', 'nearly', 'nearly'],
]);

it('refuses a reference to something the file does not contain', function () {
    $result = read(aDocument(['entities' => [anEntity('e1', ['parent_id' => 'e-missing'])]]));

    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('the parent of "A page named e1"')
        ->and($result->errors[0])->toContain('does not contain');
});

it('refuses pages that nest inside each other in a loop', function () {
    $result = read(aDocument(['entities' => [
        anEntity('e1', ['parent_id' => 'e2']),
        anEntity('e2', ['parent_id' => 'e1']),
    ]]));

    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('nest inside each other in a loop');
});

it('allows two tables to nest the same third one', function () {
    $result = read(aDocument(['random_tables' => [
        ['id' => 't1', 'name' => 'One', 'entries' => [['body' => 'a', 'nested_table_id' => 't3']]],
        ['id' => 't2', 'name' => 'Two', 'entries' => [['body' => 'b', 'nested_table_id' => 't3']]],
        ['id' => 't3', 'name' => 'Three', 'entries' => [['body' => 'c']]],
    ]]));

    // A diamond is not a loop. Only coming back to where you started is.
    expect($result->errors)->toBe([])
        ->and($result->succeeded())->toBeTrue();
});

it('refuses tables that nest each other in a loop', function () {
    $result = read(aDocument(['random_tables' => [
        ['id' => 't1', 'name' => 'One', 'entries' => [['body' => 'a', 'nested_table_id' => 't2']]],
        ['id' => 't2', 'name' => 'Two', 'entries' => [['body' => 'b', 'nested_table_id' => 't1']]],
    ]]));

    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('nest inside each other in a loop');
});

it('refuses two sessions that claim the same number', function () {
    $result = read(aDocument(['sessions' => [
        ['id' => 's1', 'number' => 1, 'status' => 'planned', 'visibility' => 'dm'],
        ['id' => 's2', 'number' => 1, 'status' => 'planned', 'visibility' => 'dm'],
    ]]));

    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('both numbered 1');
});

it('refuses two pages that share an address', function () {
    $result = read(aDocument(['entities' => [
        anEntity('e1', ['slug' => 'the-duke']),
        anEntity('e2', ['slug' => 'the-duke']),
    ]]));

    // A slug is unique per campaign, so the database would refuse this half way
    // through. Saying it here turns a foreign key violation into a sentence.
    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('share the address "the-duke"');
});

it('refuses two pages that share an id', function () {
    $result = read(aDocument(['entities' => [anEntity('e1'), anEntity('e1', ['slug' => 'other'])]]));

    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('share the id e1');
});

it('refuses a row with no id', function () {
    $result = read(aDocument(['entities' => [['type' => 'note', 'name' => 'Nameless', 'visibility' => 'dm']]]));

    expect($result->succeeded())->toBeFalse()
        ->and($result->errors[0])->toContain('has no id');
});

it('truncates an over-long name and counts it', function () {
    $result = read(aDocument(['entities' => [anEntity('e1', ['name' => str_repeat('a', 400)])]]));

    expect($result->succeeded())->toBeTrue()
        ->and($result->document['entities'][0]['name'])->toHaveLength(120)
        ->and($result->report->truncated)->toBe(1);
});

it('narrows a selected page to GM-only and counts it', function () {
    $result = read(aDocument(['entities' => [anEntity('e1', ['visibility' => 'selected'])]]));

    // Nothing is ever made more visible than the file says, and the names on a
    // selected list mean nothing on this install.
    expect($result->document['entities'][0]['visibility'])->toBe(Visibility::Dm)
        ->and($result->report->selectedLists)->toBe(1);
});

it('drops a sheet URL that is not http or https', function () {
    $result = read(aDocument(['entities' => [
        anEntity('e1', ['type' => 'character', 'sheet_url' => 'javascript:alert(1)']),
        anEntity('e2', ['type' => 'character', 'sheet_url' => 'https://example.test/wren']),
    ]]));

    expect($result->document['entities'][0]['sheet_url'])->toBeNull()
        ->and($result->document['entities'][1]['sheet_url'])->toBe('https://example.test/wren');
});

it('clamps a coordinate and a clock a file made up', function () {
    $result = read(aDocument([
        'entities' => [anEntity('m1', ['type' => 'map', 'markers' => [
            ['label' => 'Off the map', 'x' => 4000, 'y' => -12],
        ]])],
        'clocks' => [['id' => 'c1', 'name' => 'The ritual', 'segments' => 900, 'filled' => 900]],
    ]));

    expect($result->document['entities'][0]['markers'][0]['x'])->toBe(100.0)
        ->and($result->document['entities'][0]['markers'][0]['y'])->toBe(0.0)
        ->and($result->document['clocks'][0]['segments'])->toBe(12)
        ->and($result->document['clocks'][0]['filled'])->toBe(12);
});

it('counts the four things it cannot carry', function () {
    $result = read(aDocument([
        'campaign' => ['name' => 'The Drowned Duchy', 'ruleset' => 'srd-5e-2024', 'cover' => ['url' => 'https://example.test/cover.png']],
        'members' => [['name' => 'Tobin Ashgrove', 'role' => 'player'], ['name' => 'Danny', 'role' => 'owner']],
        'entities' => [anEntity('e1', [
            'visibility' => 'selected',
            'image' => ['url' => 'https://example.test/a.png'],
            'files' => [['url' => 'https://example.test/b.png'], ['url' => 'https://example.test/c.pdf']],
        ])],
        'dice_rolls' => [['id' => 'd1'], ['id' => 'd2'], ['id' => 'd3']],
    ]));

    $report = $result->report;

    expect($report->files)->toBe(4)
        ->and($report->memberNames)->toBe(['Tobin Ashgrove', 'Danny'])
        ->and($report->selectedLists)->toBe(1)
        ->and($report->diceRolls)->toBe(3)
        ->and($report->losses())->toHaveCount(4);
});

it('says nothing about losses a file does not have', function () {
    $result = read(aDocument(['entities' => [anEntity('e1')]]));

    expect($result->report->hasLosses())->toBeFalse()
        ->and($result->report->losses())->toBe([]);
});
