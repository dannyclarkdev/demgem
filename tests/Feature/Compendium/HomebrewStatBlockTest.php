<?php

use App\Actions\Compendium\CopyStatBlock;
use App\Actions\Compendium\CreateStatBlock;
use App\Actions\Compendium\DeleteStatBlock;
use App\Actions\Compendium\UpdateStatBlock;
use App\Enums\CampaignRole;
use App\Enums\Ruleset;
use App\Livewire\Compendium\Editor;
use App\Livewire\Compendium\Index;
use App\Livewire\Compendium\Show;
use App\Livewire\Encounters\Tracker;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\StatBlock;
use Livewire\Livewire;

/**
 * A campaign's own creatures, in the same table as the shipped ones and told apart by
 * one column.
 *
 * The rules that matter here are the ones a second table would have made structural and
 * a nullable column makes conditional: a shipped row is never written, never exported
 * and never claimed as a GM's; a GM's row is never touched by the loader and never
 * carries somebody else's attribution.
 */
beforeEach(function () {
    $this->campaign = Campaign::factory()->create(['ruleset' => Ruleset::Srd5e2024]);
    $this->owner = ownerOf($this->campaign);
    $this->goblin = StatBlock::factory()->named('Goblin Warrior', ['hp' => 10, 'ac' => 15])->create();
});

it('writes a creature into the campaign that asked for it', function () {
    $made = app(CreateStatBlock::class)->handle($this->campaign, [
        'name' => 'Harbour thug',
        'ac' => 13,
        'hp' => 22,
        'cr' => '1/2',
        'cr_value' => 0.5,
        'xp' => 100,
    ]);

    expect($made->campaign_id)->toBe($this->campaign->id)
        ->and($made->isShipped())->toBeFalse()
        ->and($made->ruleset)->toBe(Ruleset::Srd5e2024->value)
        ->and($made->slug)->toBe('harbour-thug')
        ->and($made->license)->toBe(StatBlock::OWN_LICENSE)
        ->and($made->source)->toBe($this->campaign->name);
});

it('suffixes a slug that a shipped creature already answers to', function () {
    $made = app(CreateStatBlock::class)->handle($this->campaign, ['name' => 'Goblin Warrior']);

    expect($made->slug)->toBe('goblin-warrior-2')
        ->and($this->goblin->refresh()->slug)->toBe('goblin-warrior');
});

it('suffixes again for a second creature of the same name', function () {
    $create = app(CreateStatBlock::class);

    $first = $create->handle($this->campaign, ['name' => 'Harbour thug']);
    $second = $create->handle($this->campaign, ['name' => 'Harbour thug']);

    expect($first->slug)->toBe('harbour-thug')
        ->and($second->slug)->toBe('harbour-thug-2');
});

it('lets two campaigns each have a creature by the same name', function () {
    $other = Campaign::factory()->create(['ruleset' => Ruleset::Generic]);

    $mine = app(CreateStatBlock::class)->handle($this->campaign, ['name' => 'Harbour thug']);
    $theirs = app(CreateStatBlock::class)->handle($other, ['name' => 'Harbour thug']);

    expect($theirs->slug)->toBe('harbour-thug')
        ->and($mine->id)->not->toBe($theirs->id);
});

it('leaves the slug alone when a creature is renamed', function () {
    $made = app(CreateStatBlock::class)->handle($this->campaign, ['name' => 'Harbour thug']);

    app(UpdateStatBlock::class)->handle($made, ['name' => 'Dock thug', 'ac' => 14]);

    // Addressed by slug everywhere, and a slug that moves is not an address. This is
    // the opposite call from an entity, and UpdateStatBlock records why.
    expect($made->refresh()->name)->toBe('Dock thug')
        ->and($made->slug)->toBe('harbour-thug')
        ->and($made->ac)->toBe(14);
});

it('refuses to edit or delete a shipped creature, whatever the caller', function () {
    expect(fn () => app(UpdateStatBlock::class)->handle($this->goblin, ['name' => 'Rewritten']))
        ->toThrow(RuntimeException::class);

    expect(fn () => app(DeleteStatBlock::class)->handle($this->goblin))
        ->toThrow(RuntimeException::class);

    expect($this->goblin->refresh()->name)->toBe('Goblin Warrior');
});

it('copies a shipped creature with its attribution attached', function () {
    $copy = app(CopyStatBlock::class)->handle($this->campaign, $this->goblin);

    // Copying CC BY material into a campaign is what the licence allows; carrying the
    // notice with the words is what it asks in return.
    expect($copy->campaign_id)->toBe($this->campaign->id)
        ->and($copy->slug)->toBe('goblin-warrior-2')
        ->and($copy->name)->toBe('Goblin Warrior')
        ->and($copy->source)->toBe($this->goblin->source)
        ->and($copy->license)->toBe($this->goblin->license)
        ->and($copy->ac)->toBe(15)
        ->and($copy->hp)->toBe(10);
});

it('duplicates a campaign creature keeping the campaign as its source', function () {
    $made = app(CreateStatBlock::class)->handle($this->campaign, ['name' => 'Harbour thug']);

    $copy = app(CopyStatBlock::class)->handle($this->campaign, $made);

    expect($copy->license)->toBe(StatBlock::OWN_LICENSE)
        ->and($copy->source)->toBe($this->campaign->name)
        ->and($copy->slug)->toBe('harbour-thug-2');
});

it('keeps one campaign out of another campaign book', function () {
    $other = Campaign::factory()->create(['ruleset' => Ruleset::Srd5e2024]);

    app(CreateStatBlock::class)->handle($other, ['name' => 'Their secret horror']);

    Livewire::actingAs($this->owner)
        ->test(Index::class, ['campaign' => $this->campaign])
        ->assertOk()
        ->assertSee('Goblin Warrior')
        ->assertDontSee('Their secret horror');
});

it('puts the campaign own creatures first in the book', function () {
    // A shipped goblin sorts at CR 1; this one is worth more and would sort after it.
    app(CreateStatBlock::class)->handle($this->campaign, [
        'name' => 'Harbour thug',
        'cr' => '9',
        'cr_value' => 9.0,
    ]);

    $ordered = StatBlock::query()->forCampaign($this->campaign)->ownFirst()->pluck('name')->all();

    expect($ordered[0])->toBe('Harbour thug');
});

it('never lets the loader touch a creature a GM wrote', function () {
    $made = app(CreateStatBlock::class)->handle($this->campaign, [
        'name' => 'Goblin Warrior',
        'ac' => 99,
    ]);

    // The loader upserts on (ruleset, slug) among the shipped rows only. Without the
    // shipped() scope this row's slug could be updated out from under the GM, or
    // reported as one the dataset no longer names.
    $this->artisan('demgem:import-srd')->assertSuccessful();

    expect($made->refresh()->ac)->toBe(99)
        ->and($made->campaign_id)->toBe($this->campaign->id)
        ->and($made->name)->toBe('Goblin Warrior');
});

it('gives a fight a creature the GM wrote, numbers and all', function () {
    $made = app(CreateStatBlock::class)->handle($this->campaign, [
        'name' => 'Harbour thug',
        'ac' => 13,
        'hp' => 22,
        'initiative_bonus' => 2,
        'legendary_action_uses' => 2,
    ]);

    $encounter = Encounter::factory()->for($this->campaign)->create();

    Livewire::actingAs($this->owner)
        ->test(Tracker::class, ['campaign' => $this->campaign, 'encounter' => $encounter])
        ->call('addFromCompendium', $made->id);

    $combatant = $encounter->combatants()->sole();

    expect($combatant->name)->toBe('Harbour thug')
        ->and($combatant->ac)->toBe(13)
        ->and($combatant->hp)->toBe(22)
        ->and($combatant->initiative_bonus)->toBe(2)
        ->and($combatant->legendary_actions_max)->toBe(2)
        ->and($combatant->stat_block_id)->toBe($made->id);
});

it('prices a fight from a creature the GM wrote', function () {
    $made = app(CreateStatBlock::class)->handle($this->campaign, ['name' => 'Harbour thug', 'xp' => 450]);

    $encounter = Encounter::factory()->for($this->campaign)->create();

    Combatant::factory()->inEncounter($encounter)->create(['stat_block_id' => $made->id]);

    Livewire::actingAs($this->owner)
        ->test(Tracker::class, ['campaign' => $this->campaign, 'encounter' => $encounter])
        ->assertViewHas('spent', 450)
        ->assertViewHas('unpriced', 0);
});

it('leaves a fight standing when the creature it came from is deleted', function () {
    $made = app(CreateStatBlock::class)->handle($this->campaign, ['name' => 'Harbour thug', 'ac' => 13, 'hp' => 22]);

    $encounter = Encounter::factory()->for($this->campaign)->create();

    Livewire::actingAs($this->owner)
        ->test(Tracker::class, ['campaign' => $this->campaign, 'encounter' => $encounter])
        ->call('addFromCompendium', $made->id);

    app(DeleteStatBlock::class)->handle($made);

    $combatant = $encounter->combatants()->sole();

    expect($combatant->stat_block_id)->toBeNull()
        ->and($combatant->name)->toBe('Harbour thug')
        ->and($combatant->ac)->toBe(13)
        ->and($combatant->hp)->toBe(22);
});

it('shows no SRD notice under a creature a GM wrote', function () {
    $made = app(CreateStatBlock::class)->handle($this->campaign, ['name' => 'Harbour thug']);

    Livewire::actingAs($this->owner)
        ->test(Show::class, ['campaign' => $this->campaign, 'statBlockSlug' => $made->slug])
        ->assertOk()
        ->assertSee('Yours')
        ->assertDontSee('System Reference Document');

    Livewire::actingAs($this->owner)
        ->test(Show::class, ['campaign' => $this->campaign, 'statBlockSlug' => 'goblin-warrior'])
        ->assertSee('System Reference Document');
});

it('writes a creature from the form and lands the GM on it', function () {
    Livewire::actingAs($this->owner)
        ->test(Editor::class, ['campaign' => $this->campaign])
        ->set('name', 'Harbour thug')
        ->set('cr', '1/2')
        ->set('ac', 13)
        ->set('abilities.str.score', 14)
        ->call('addSectionEntry', 'actions')
        ->set('sections.actions.0.name', 'Cudgel')
        ->set('sections.actions.0.text', 'It swings a cudgel.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $made = StatBlock::query()->ownedBy($this->campaign)->sole();

    expect($made->name)->toBe('Harbour thug')
        ->and($made->cr)->toBe('1/2')
        // The label is typed and the number follows it, so the list sorts and filters.
        ->and($made->cr_value)->toBe(0.5)
        ->and($made->ac)->toBe(13)
        // The modifier follows the score rather than being asked for twice.
        ->and($made->ability_scores['str'])->toBe(['score' => 14, 'mod' => '+2', 'save' => '+2'])
        ->and($made->actions)->toBe([['name' => 'Cudgel', 'text' => 'It swings a cudgel.']]);
});

it('suggests the XP for a rating and then leaves it alone', function () {
    Livewire::actingAs($this->owner)
        ->test(Editor::class, ['campaign' => $this->campaign])
        ->set('cr', '3')
        ->assertSet('xp', 700)
        ->set('xp', 1200)
        ->set('cr', '4')
        ->assertSet('xp', 1200);
});

it('will not open the editor on a shipped creature', function () {
    Livewire::actingAs($this->owner)
        ->test(Editor::class, ['campaign' => $this->campaign, 'statBlockSlug' => 'goblin-warrior'])
        ->assertForbidden();
});

it('refuses a player everywhere', function () {
    $player = memberOf($this->campaign, CampaignRole::Player);
    $made = app(CreateStatBlock::class)->handle($this->campaign, ['name' => 'Harbour thug']);

    Livewire::actingAs($player)
        ->test(Editor::class, ['campaign' => $this->campaign])
        ->assertForbidden();

    Livewire::actingAs($player)
        ->test(Editor::class, ['campaign' => $this->campaign, 'statBlockSlug' => $made->slug])
        ->assertForbidden();
});
