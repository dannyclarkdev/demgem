<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Actions\Compendium\CreateStatBlock;
use App\Enums\EntityType;
use App\Enums\Ruleset;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\StatBlock;
use App\Models\User;

/**
 * A campaign's own creatures travel; the shipped ones are named and left behind.
 *
 * The two halves are the same table and the opposite promise, which is the whole reason
 * this file exists beside the round trip test. That one compares documents and drops
 * every remapped id, so the own-creature link is proven here instead.
 */
beforeEach(function () {
    $this->campaign = Campaign::factory()->create([
        'name' => 'The Drowned Duchy',
        'ruleset' => Ruleset::Srd5e2024,
    ]);

    $this->goblin = StatBlock::factory()->named('Goblin Warrior', ['hp' => 10, 'ac' => 15])->create();

    $this->thug = app(CreateStatBlock::class)->handle($this->campaign, [
        'name' => 'Harbour thug',
        'ac' => 13,
        'hp' => 22,
        'cr' => '1/2',
        'cr_value' => 0.5,
        'xp' => 100,
        'traits' => [['name' => 'Sea legs', 'text' => 'It ignores difficult terrain on a deck.']],
    ]);

    Entity::factory()->for($this->campaign)->create([
        'type' => EntityType::Character,
        'name' => 'The Drowned Duke',
        'stat_block_id' => $this->thug->id,
    ]);

    $encounter = Encounter::factory()->for($this->campaign)->create(['name' => 'The flooded stair']);

    Combatant::factory()->inEncounter($encounter, 0)->create([
        'campaign_id' => $this->campaign->id,
        'name' => 'Harbour thug 1',
        'stat_block_id' => $this->thug->id,
    ]);

    Combatant::factory()->inEncounter($encounter, 1)->create([
        'campaign_id' => $this->campaign->id,
        'name' => 'Goblin Warrior 1',
        'stat_block_id' => $this->goblin->id,
    ]);
});

it('carries a creature the campaign wrote, prose and all', function () {
    $document = exportedArray($this->campaign);

    $written = collect($document['stat_blocks'])->firstWhere('name', 'Harbour thug');

    expect($document['stat_blocks'])->toHaveCount(1)
        ->and($written['ac'])->toBe(13)
        ->and($written['cr'])->toBe('1/2')
        ->and($written['xp'])->toBe(100)
        // The GM's own words, in the GM's own export.
        ->and($written['traits'])->toBe([['name' => 'Sea legs', 'text' => 'It ignores difficult terrain on a deck.']]);
});

it('names a shipped creature and carries none of its prose', function () {
    $document = exportedArray($this->campaign);

    $goblinRow = collect($document['encounters'][0]['combatants'])->firstWhere('name', 'Goblin Warrior 1');
    $thugRow = collect($document['encounters'][0]['combatants'])->firstWhere('name', 'Harbour thug 1');

    expect($goblinRow['stat_block'])->toBe(['ruleset' => 'srd-5e-2024', 'slug' => 'goblin-warrior'])
        ->and($goblinRow['stat_block_id'])->toBeNull()
        // The campaign's own is a campaign row: an id, remapped like every other.
        ->and($thugRow['stat_block'])->toBeNull()
        ->and($thugRow['stat_block_id'])->toBe($this->thug->id);

    $encoded = json_encode($document, JSON_THROW_ON_ERROR);

    expect($encoded)->not->toContain('Melee Attack Roll');
});

it('reads the creature back and re-links everything that pointed at it', function () {
    $document = exportedArray($this->campaign);

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));
    $copy = app(ImportCampaign::class)->handle($read->document, User::factory()->create());

    $written = StatBlock::query()->ownedBy($copy)->sole();

    expect($written->id)->not->toBe($this->thug->id)
        ->and($written->name)->toBe('Harbour thug')
        ->and($written->ac)->toBe(13)
        ->and($written->traits)->toBe([['name' => 'Sea legs', 'text' => 'It ignores difficult terrain on a deck.']]);

    $duke = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)
        ->where('name', 'The Drowned Duke')->sole();

    $encounter = Encounter::withoutGlobalScopes()->where('campaign_id', $copy->id)->sole();
    $thugRow = $encounter->combatants()->where('name', 'Harbour thug 1')->sole();
    $goblinRow = $encounter->combatants()->where('name', 'Goblin Warrior 1')->sole();

    expect($duke->stat_block_id)->toBe($written->id)
        ->and($thugRow->stat_block_id)->toBe($written->id)
        // The shipped one is found by its natural key, and it is the same global row.
        ->and($goblinRow->stat_block_id)->toBe($this->goblin->id);
});

it('refuses a file that points at a creature it does not carry', function () {
    $document = exportedArray($this->campaign);

    $document['stat_blocks'] = [];

    $result = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($result->succeeded())->toBeFalse()
        ->and(implode(' ', $result->errors))->toContain('does not carry');
});

it('files an imported creature under the new campaign own ruleset', function () {
    $document = exportedArray($this->campaign);
    $document['campaign']['ruleset'] = Ruleset::Generic->value;

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));
    $copy = app(ImportCampaign::class)->handle($read->document, User::factory()->create());

    // A creature filed under a ruleset its own campaign does not run would sit in a
    // compendium that campaign cannot see.
    expect(StatBlock::query()->ownedBy($copy)->sole()->ruleset)->toBe(Ruleset::Generic->value);
});
