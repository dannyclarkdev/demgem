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

it('refuses a campaign with no compendium', function () {
    $generic = Campaign::factory()->create(['ruleset' => Ruleset::Generic]);

    asKey(ownerOf($generic))
        ->getJson(route('api.stat-blocks.index', $generic))
        ->assertForbidden();
});

it('refuses a request with no key at all', function () {
    withoutKey()
        ->getJson(route('api.stat-blocks.index', $this->campaign))
        ->assertUnauthorized();
});

it('has no way to write a stat block', function () {
    asKey($this->owner, write: true)
        ->postJson(route('api.stat-blocks.index', $this->campaign), ['name' => 'Homebrew Horror'])
        ->assertStatus(405);

    expect(StatBlock::query()->where('name', 'Homebrew Horror')->exists())->toBeFalse();
});
