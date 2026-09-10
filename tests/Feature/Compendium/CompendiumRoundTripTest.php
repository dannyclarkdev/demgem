<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Enums\EntityType;
use App\Enums\Ruleset;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\StatBlock;
use App\Models\User;

beforeEach(function () {
    $this->campaign = Campaign::factory()->create(['ruleset' => Ruleset::Srd5e2024]);
    $this->owner = ownerOf($this->campaign);
    $this->goblin = StatBlock::factory()->named('Goblin Warrior', ['hp' => 10, 'ac' => 15])->create();

    Entity::factory()->for($this->campaign)->create([
        'type' => EntityType::Character,
        'name' => 'The Drowned Duke',
        'stat_block_id' => $this->goblin->id,
    ]);

    $encounter = Encounter::factory()->for($this->campaign)->create();

    Combatant::factory()->inEncounter($encounter)->create([
        'campaign_id' => $this->campaign->id,
        'name' => 'Goblin Warrior 1',
        'stat_block_id' => $this->goblin->id,
        'hp' => 10,
        'ac' => 15,
    ]);
});

it('exports the reference and never the prose', function () {
    $document = exportedArray($this->campaign);

    $entity = collect($document['entities'])->firstWhere('name', 'The Drowned Duke');
    $combatant = $document['encounters'][0]['combatants'][0];

    expect($entity['stat_block'])->toBe(['ruleset' => 'srd-5e-2024', 'slug' => 'goblin-warrior'])
        ->and($combatant['stat_block'])->toBe(['ruleset' => 'srd-5e-2024', 'slug' => 'goblin-warrior']);

    // The prose stays in the compendium. A GM's export carries no licensed text.
    expect(json_encode($document))->not->toContain('Scimitar');
});

it('restores the links on an install that has the dataset', function () {
    $document = exportedArray($this->campaign);

    $result = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($result->errors)->toBe([])
        ->and($result->report->statBlocks)->toBe(0);

    $imported = app(ImportCampaign::class)->handle($result->document, User::factory()->create());

    $entity = Entity::withoutGlobalScopes()->where('campaign_id', $imported->id)->where('name', 'The Drowned Duke')->sole();
    $combatant = Combatant::withoutGlobalScopes()->where('campaign_id', $imported->id)->sole();

    expect($entity->stat_block_id)->toBe($this->goblin->id)
        ->and($combatant->stat_block_id)->toBe($this->goblin->id);
});

it('imports without the links when the install has no dataset', function () {
    $document = exportedArray($this->campaign);

    StatBlock::query()->delete();

    $result = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($result->errors)->toBe([])
        ->and($result->report->statBlocks)->toBe(2);

    $imported = app(ImportCampaign::class)->handle($result->document, User::factory()->create());

    $entity = Entity::withoutGlobalScopes()->where('campaign_id', $imported->id)->where('name', 'The Drowned Duke')->sole();
    $combatant = Combatant::withoutGlobalScopes()->where('campaign_id', $imported->id)->sole();

    expect($entity->stat_block_id)->toBeNull()
        ->and($combatant->stat_block_id)->toBeNull()
        // The numbers are on the row, so the fight still works.
        ->and($combatant->hp)->toBe(10)
        ->and($combatant->ac)->toBe(15);

    $labels = collect($result->report->losses())->pluck('label')->implode(' ');

    expect($labels)->toContain('compendium');
});
