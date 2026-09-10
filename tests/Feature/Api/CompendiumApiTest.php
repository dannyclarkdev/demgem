<?php

use App\Enums\CampaignRole;
use App\Enums\Ruleset;
use App\Models\Campaign;
use App\Models\StatBlock;

beforeEach(function () {
    $this->campaign = Campaign::factory()->create(['ruleset' => Ruleset::Srd5e2024]);
    $this->owner = ownerOf($this->campaign);

    StatBlock::factory()->named('Goblin Warrior', ['creature_type' => 'Fey', 'cr' => '1/4', 'cr_value' => 0.25])->create();
    StatBlock::factory()->named('Adult Red Dragon', ['creature_type' => 'Dragon', 'cr' => '17', 'cr_value' => 17])->create();
});

it('lists the compendium for a GM key', function () {
    asKey($this->owner)
        ->getJson(route('api.stat-blocks.index', $this->campaign))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Goblin Warrior')
        ->assertJsonPath('data.1.name', 'Adult Red Dragon');
});

it('filters by name, type and challenge', function () {
    asKey($this->owner)
        ->getJson(route('api.stat-blocks.index', [$this->campaign, 'q' => 'goblin']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Goblin Warrior');

    asKey($this->owner)
        ->getJson(route('api.stat-blocks.index', [$this->campaign, 'type' => 'Dragon']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Adult Red Dragon');

    asKey($this->owner)
        ->getJson(route('api.stat-blocks.index', [$this->campaign, 'cr_min' => 5]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Adult Red Dragon');
});

it('reads one creature by slug, with its attribution', function () {
    asKey($this->owner)
        ->getJson(route('api.stat-blocks.show', [$this->campaign, 'goblin-warrior']))
        ->assertOk()
        ->assertJsonPath('data.name', 'Goblin Warrior')
        ->assertJsonPath('data.source', 'SRD 5.2.1')
        ->assertJsonPath('data.license', 'CC-BY-4.0')
        ->assertJsonPath('data.attribution', config('compendium.attribution'));
});

it('refuses a player key', function () {
    $player = memberOf($this->campaign, CampaignRole::Player);

    asKey($player)
        ->getJson(route('api.stat-blocks.index', $this->campaign))
        ->assertForbidden();

    asKey($player)
        ->getJson(route('api.stat-blocks.show', [$this->campaign, 'goblin-warrior']))
        ->assertForbidden();
});

it('serves a campaign with no shipped book its own creatures and nothing else', function () {
    $generic = Campaign::factory()->create(['ruleset' => Ruleset::Generic]);

    // Slice 17 dropped the ruleset half of the gate: every campaign can write
    // creatures, so every campaign has a compendium. What the ruleset still decides is
    // what is in it.
    asKey(ownerOf($generic))
        ->getJson(route('api.stat-blocks.index', $generic))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('refuses a request with no key at all', function () {
    withoutKey()
        ->getJson(route('api.stat-blocks.index', $this->campaign))
        ->assertUnauthorized();
});

it('writes a creature for the campaign, and never a shipped one', function () {
    asKey($this->owner, write: true)
        ->postJson(route('api.stat-blocks.store', $this->campaign), [
            'name' => 'Homebrew Horror',
            'ac' => 15,
            'hp' => 44,
            'cr' => '3',
            'xp' => 700,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Homebrew Horror')
        ->assertJsonPath('data.own', true)
        // Not SRD material, so no notice claiming it is.
        ->assertJsonMissingPath('data.attribution');

    $made = StatBlock::query()->where('name', 'Homebrew Horror')->sole();

    expect($made->campaign_id)->toBe($this->campaign->id)
        ->and($made->ruleset)->toBe($this->campaign->ruleset->value);
});

it('will not edit a shipped creature through a key that can write', function () {
    asKey($this->owner, write: true)
        ->patchJson(route('api.stat-blocks.update', [$this->campaign, 'goblin-warrior']), ['name' => 'Rewritten'])
        ->assertNotFound();

    expect(StatBlock::query()->where('slug', 'goblin-warrior')->value('name'))->not->toBe('Rewritten');
});

it('refuses a key without write access', function () {
    asKey($this->owner)
        ->postJson(route('api.stat-blocks.store', $this->campaign), ['name' => 'Homebrew Horror'])
        ->assertForbidden();

    expect(StatBlock::query()->where('name', 'Homebrew Horror')->exists())->toBeFalse();
});

it('names the fields a key may not set', function () {
    asKey($this->owner, write: true)
        ->postJson(route('api.stat-blocks.store', $this->campaign), [
            'name' => 'Homebrew Horror',
            'slug' => 'something-i-picked',
            'license' => 'CC-BY-4.0',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['slug', 'license']);
});
