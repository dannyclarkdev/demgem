<?php

use App\Enums\CampaignRole;
use App\Enums\Ruleset;
use App\Livewire\Compendium\Index;
use App\Livewire\Compendium\Show;
use App\Models\Campaign;
use App\Models\StatBlock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->campaign = Campaign::factory()->create(['ruleset' => Ruleset::Srd5e2024]);
    $this->owner = ownerOf($this->campaign);

    $this->goblin = StatBlock::factory()->named('Goblin Warrior', [
        'creature_type' => 'Fey',
        'cr' => '1/4',
        'cr_value' => 0.25,
    ])->create();

    $this->dragon = StatBlock::factory()->named('Adult Red Dragon', [
        'creature_type' => 'Dragon',
        'cr' => '17',
        'cr_value' => 17,
    ])->create();
});

it('lists the creatures weakest first', function () {
    Livewire::actingAs($this->owner)
        ->test(Index::class, ['campaign' => $this->campaign])
        ->assertOk()
        ->assertSeeInOrder(['Goblin Warrior', 'Adult Red Dragon']);
});

it('searches by name', function () {
    Livewire::actingAs($this->owner)
        ->test(Index::class, ['campaign' => $this->campaign])
        ->set('search', 'goblin')
        ->assertSee('Goblin Warrior')
        ->assertDontSee('Adult Red Dragon');
});

it('filters by creature type', function () {
    Livewire::actingAs($this->owner)
        ->test(Index::class, ['campaign' => $this->campaign])
        ->set('creatureType', 'Dragon')
        ->assertSee('Adult Red Dragon')
        ->assertDontSee('Goblin Warrior');
});

it('filters by challenge band', function () {
    Livewire::actingAs($this->owner)
        ->test(Index::class, ['campaign' => $this->campaign])
        ->set('challenge', 'deadly')
        ->assertSee('Adult Red Dragon')
        ->assertDontSee('Goblin Warrior');
});

it('shows one creature as the book prints it', function () {
    Livewire::actingAs($this->owner)
        ->test(Show::class, ['campaign' => $this->campaign, 'statBlockSlug' => 'goblin-warrior'])
        ->assertOk()
        ->assertSee('Goblin Warrior')
        ->assertSee('Medium Humanoid, Neutral');
});

it('carries the attribution on both screens', function () {
    $notice = config('compendium.attribution');

    Livewire::actingAs($this->owner)
        ->test(Index::class, ['campaign' => $this->campaign])
        ->assertSee($notice);

    Livewire::actingAs($this->owner)
        ->test(Show::class, ['campaign' => $this->campaign, 'statBlockSlug' => 'goblin-warrior'])
        ->assertSee($notice);
});

it('refuses a player', function () {
    $player = memberOf($this->campaign, CampaignRole::Player);

    Livewire::actingAs($player)
        ->test(Index::class, ['campaign' => $this->campaign])
        ->assertForbidden();

    Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $this->campaign, 'statBlockSlug' => 'goblin-warrior'])
        ->assertForbidden();
});

it('refuses a GM whose campaign has no compendium', function () {
    $generic = Campaign::factory()->create(['ruleset' => Ruleset::Generic]);

    Livewire::actingAs(ownerOf($generic))
        ->test(Index::class, ['campaign' => $generic])
        ->assertForbidden();
});

it('keeps the compendium out of a system-agnostic campaign\'s nav', function () {
    $generic = Campaign::factory()->create(['ruleset' => Ruleset::Generic]);

    $this->actingAs(ownerOf($generic))
        ->get(route('campaigns.show', $generic))
        ->assertOk()
        ->assertDontSee('Compendium');

    $this->actingAs($this->owner)
        ->get(route('campaigns.show', $this->campaign))
        ->assertOk()
        ->assertSee('Compendium');
});

it('will not read a stat block from another ruleset', function () {
    StatBlock::factory()->named('Clockwork Hound', ['ruleset' => 'some-other-system'])->create();

    expect(fn () => Livewire::actingAs($this->owner)
        ->test(Show::class, ['campaign' => $this->campaign, 'statBlockSlug' => 'clockwork-hound']))
        ->toThrow(ModelNotFoundException::class);
});
