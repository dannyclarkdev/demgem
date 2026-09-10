<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\User;

/**
 * A fight mid-death-save is still a campaign, and a GM who exports one gets it back.
 *
 * The round trip test compares whole documents and is driven by SECTION_TABLES, so it
 * covers the shape. This covers the values: five new columns on a combatant and two on
 * an encounter, each of which the reader could quietly drop and nothing else would say.
 */
beforeEach(function () {
    $this->campaign = Campaign::factory()->create(['name' => 'The Drowned Duchy']);

    $encounter = Encounter::factory()->for($this->campaign)->create([
        'name' => 'The flooded stair',
        'lair_action_note' => 'The water rises one step.',
        'lair_initiative' => 20,
    ]);

    Combatant::factory()->inEncounter($encounter)->down(40)->withLegendaryActions(3)->create([
        'name' => 'The Drowned Duke',
        'concentrating_on' => 'Hold Person',
        'death_save_successes' => 1,
        'death_save_failures' => 2,
        'legendary_actions_left' => 1,
    ]);
});

it('writes every one of them into the document', function () {
    $document = exportedArray($this->campaign);

    $encounter = $document['encounters'][0];
    $combatant = $encounter['combatants'][0];

    expect($encounter['lair_action_note'])->toBe('The water rises one step.')
        ->and($encounter['lair_initiative'])->toBe(20)
        ->and($combatant['concentrating_on'])->toBe('Hold Person')
        ->and($combatant['death_save_successes'])->toBe(1)
        ->and($combatant['death_save_failures'])->toBe(2)
        ->and($combatant['legendary_actions_max'])->toBe(3)
        ->and($combatant['legendary_actions_left'])->toBe(1);
});

it('reads them back onto the rows they came off', function () {
    $document = exportedArray($this->campaign);

    $importer = User::factory()->create();
    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));
    $copy = app(ImportCampaign::class)->handle($read->document, $importer);

    $encounter = Encounter::withoutGlobalScopes()->where('campaign_id', $copy->id)
        ->where('name', 'The flooded stair')->sole();
    $duke = $encounter->combatants()->where('name', 'The Drowned Duke')->sole();

    expect($encounter->lair_action_note)->toBe('The water rises one step.')
        ->and($encounter->lairInitiative())->toBe(20)
        ->and($duke->concentrating_on)->toBe('Hold Person')
        ->and($duke->death_save_successes)->toBe(1)
        ->and($duke->death_save_failures)->toBe(2)
        ->and($duke->legendary_actions_max)->toBe(3)
        ->and($duke->legendary_actions_left)->toBe(1);
});

it('holds a count from a file inside the range the application allows', function () {
    $document = exportedArray($this->campaign);

    $document['encounters'][0]['combatants'][0]['death_save_failures'] = 99;
    $document['encounters'][0]['combatants'][0]['legendary_actions_max'] = 4000;

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));
    $copy = app(ImportCampaign::class)->handle($read->document, User::factory()->create());

    $duke = Encounter::withoutGlobalScopes()->where('campaign_id', $copy->id)
        ->sole()->combatants()->sole();

    expect($duke->death_save_failures)->toBe(Combatant::DEATH_SAVES)
        ->and($duke->legendary_actions_max)->toBe(Combatant::MAX_LEGENDARY_ACTIONS);
});
