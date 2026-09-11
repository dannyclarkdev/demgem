<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Actions\RandomTables\InstallGenerator;
use App\Actions\RandomTables\RollRandomTable;
use App\Enums\CampaignRole;
use App\Livewire\RandomTables\Index;
use App\Models\Campaign;
use App\Models\RandomTable;
use App\Models\User;
use App\Support\Generators\Generators;
use Livewire\Livewire;

it('ships six sets that read cleanly and nest within themselves', function () {
    $sets = app(Generators::class)->all();

    expect($sets->keys()->sort()->values()->all())->toBe(['loot', 'names', 'npc', 'rumor', 'tavern', 'weather']);

    foreach ($sets as $set) {
        expect($set->tableCount())->toBeGreaterThan(0)
            ->and($set->entryCount())->toBeGreaterThan(9);

        foreach ($set->tables as $table) {
            foreach ($table['entries'] as $entry) {
                expect(mb_strlen($entry['body']))->toBeLessThanOrEqual(300)
                    ->and($entry['weight'])->toBeGreaterThan(0);
            }
        }
    }
});

it('refuses a file whose nesting points outside the set', function () {
    $dir = sys_get_temp_dir().'/demgem-generators-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/bad.json', json_encode([
        'key' => 'bad', 'name' => 'Bad', 'description' => 'A broken set.',
        'tables' => [['slug' => 'a', 'name' => 'A', 'entries' => [['body' => 'x', 'nested' => 'missing']]]],
    ]));

    expect(fn () => (new Generators($dir))->all())->toThrow(RuntimeException::class, 'nests missing');
});

it('installs a set as ordinary tables, stamped, with the chain resolved', function () {
    $campaign = Campaign::factory()->create();
    $set = app(Generators::class)->find('npc');

    $made = app(InstallGenerator::class)->handle($campaign, ownerOf($campaign), $set);

    expect($made)->toHaveCount(4)
        ->and(RandomTable::withoutGlobalScopes()->where('campaign_id', $campaign->id)->where('generator_key', 'npc')->count())->toBe(4);

    $who = $made->first();
    $entry = $who->entries()->first();

    expect($who->name)->toBe('Someone the party meets')
        ->and($entry?->nestedTable?->name)->toBe('Someone the party meets: what they want');

    // A roll runs the whole chain: who, wants, hides, manner.
    $results = app(RollRandomTable::class)->handle($who);

    expect($results)->toHaveCount(4)
        ->and($results[3]['table'])->toBe('Someone the party meets: how they carry it');
});

it('renames a table whose name the campaign already uses, and keeps the chain', function () {
    $campaign = Campaign::factory()->create();
    RandomTable::factory()->for($campaign)->create(['name' => 'The weather']);
    $set = app(Generators::class)->find('weather');

    $made = app(InstallGenerator::class)->handle($campaign, ownerOf($campaign), $set);

    expect($made->first()?->name)->toBe('The weather (generator)')
        ->and($made->first()?->entries()->first()?->nestedTable?->name)->toBe('The weather: by nightfall');
});

it('refuses to install a set twice, and counts it as in while any table carries the key', function () {
    $campaign = Campaign::factory()->create();
    $set = app(Generators::class)->find('weather');
    $installer = app(InstallGenerator::class);

    $made = $installer->handle($campaign, ownerOf($campaign), $set);

    expect(fn () => $installer->handle($campaign, ownerOf($campaign), $set))->toThrow(InvalidArgumentException::class);

    $made->last()?->delete();

    expect(InstallGenerator::isInstalled($campaign, $set))->toBeTrue();

    $made->first()?->delete();

    expect(InstallGenerator::isInstalled($campaign, $set))->toBeFalse();
});

it('offers the sets on the tables index and adds one, or all, on a press', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->assertSee('Generators')
        ->assertSee('Someone the party meets')
        ->assertSee('Add all')
        ->call('install', 'npc')
        ->assertSee('Added')
        ->call('installAll')
        ->assertDontSee('Add all');

    expect(RandomTable::withoutGlobalScopes()->where('campaign_id', $campaign->id)->whereNotNull('generator_key')->distinct()->count('generator_key'))->toBe(6);
});

it('refuses a player the sets, like any table', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $this->actingAs($player)->get(route('tables.index', $campaign))->assertForbidden();

    Livewire::actingAs($player)
        ->test(Index::class, ['campaign' => $campaign])
        ->assertForbidden();
});

it('carries the key through the round trip', function () {
    $campaign = Campaign::factory()->create();
    app(InstallGenerator::class)->handle($campaign, ownerOf($campaign), app(Generators::class)->find('weather'));

    $document = exportedArray($campaign);

    expect(collect($document['random_tables'])->pluck('generator_key')->unique()->all())->toBe(['weather']);

    $importer = User::factory()->create();
    $copy = app(ImportCampaign::class)->handle(app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR))->document, $importer);

    expect(InstallGenerator::isInstalled($copy, app(Generators::class)->find('weather')))->toBeTrue();
});
