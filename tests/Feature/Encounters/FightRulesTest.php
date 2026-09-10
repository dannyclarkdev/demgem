<?php

use App\Actions\Encounters\ApplyDamage;
use App\Enums\Ruleset;
use App\Livewire\Encounters\Tracker;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\StatBlock;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The four things a 5e table used to track on paper: concentration, death saves,
 * legendary actions, and the lair.
 *
 * All of them hang off numbers the GM was already typing, so none of them needs a
 * second click to stay right.
 */
function aFight(?Campaign $campaign = null): Encounter
{
    $campaign ??= Campaign::factory()->create();

    return Encounter::factory()->for($campaign)->create();
}

function trackerFor(Encounter $encounter): Testable
{
    return Livewire::actingAs(ownerOf($encounter->campaign))
        ->test(Tracker::class, ['campaign' => $encounter->campaign, 'encounter' => $encounter]);
}

it('holds one named effect per row', function () {
    $encounter = aFight();
    $combatant = Combatant::factory()->inEncounter($encounter)->withHealth(30)->create();

    trackerFor($encounter)
        ->call('openRules', $combatant->id)
        ->set('newConcentration', 'Hold Person')
        ->call('saveRules', $combatant->id)
        ->assertHasNoErrors();

    expect($combatant->refresh()->concentrating_on)->toBe('Hold Person')
        ->and($combatant->isConcentrating())->toBeTrue();
});

it('asks for a save of ten or half the damage, whichever is more', function () {
    expect(ApplyDamage::concentrationDc(6))->toBe(10)
        ->and(ApplyDamage::concentrationDc(20))->toBe(10)
        ->and(ApplyDamage::concentrationDc(21))->toBe(10)
        ->and(ApplyDamage::concentrationDc(22))->toBe(11)
        ->and(ApplyDamage::concentrationDc(45))->toBe(22);
});

it('prompts the save when damage lands on a concentrating row', function () {
    $encounter = aFight();
    $combatant = Combatant::factory()->inEncounter($encounter)
        ->withHealth(40)->concentratingOn('Bless')->create();

    trackerFor($encounter)
        ->call('openDamage', $combatant->id)
        ->set('damage', '30')
        ->call('applyDamage', $combatant->id, 1)
        ->assertSet('concentrationDc', 15)
        ->assertSet('concentrationDcFor', $combatant->id)
        ->assertSee('rolls a DC 15 save');

    // Nothing is rolled and nothing is dropped: the GM rolls it.
    expect($combatant->refresh()->concentrating_on)->toBe('Bless');
});

it('does not prompt a save for healing, or for a row holding nothing', function () {
    $encounter = aFight();
    $holding = Combatant::factory()->inEncounter($encounter)->withHealth(40)->concentratingOn('Bless')->create();
    $empty = Combatant::factory()->inEncounter($encounter, 1)->withHealth(40)->create();

    trackerFor($encounter)
        ->call('openDamage', $holding->id)
        ->set('damage', '10')
        ->call('applyDamage', $holding->id, -1)
        ->assertSet('concentrationDc', null);

    trackerFor($encounter)
        ->call('openDamage', $empty->id)
        ->set('damage', '30')
        ->call('applyDamage', $empty->id, 1)
        ->assertSet('concentrationDc', null);
});

it('ends concentration when the row drops to nought', function () {
    $encounter = aFight();
    $combatant = Combatant::factory()->inEncounter($encounter)
        ->withHealth(12)->concentratingOn('Hold Person')->create();

    app(ApplyDamage::class)->handle($combatant, 30);

    expect($combatant->refresh()->concentrating_on)->toBeNull()
        ->and($combatant->isDown())->toBeTrue();
});

it('records a failed death save when damage lands on a row already on nought', function () {
    $encounter = aFight();
    $combatant = Combatant::factory()->inEncounter($encounter)->down()->create();

    app(ApplyDamage::class)->handle($combatant, 5);

    expect($combatant->refresh()->death_save_failures)->toBe(1)
        ->and($combatant->death_save_successes)->toBe(0);
});

it('ends the question at three of either', function () {
    $encounter = aFight();
    $combatant = Combatant::factory()->inEncounter($encounter)->down()->create();

    $tracker = trackerFor($encounter);

    foreach (range(1, 5) as $ignored) {
        $tracker->call('deathSaveFailure', $combatant->id);
    }

    expect($combatant->refresh()->death_save_failures)->toBe(Combatant::DEATH_SAVES)
        ->and($combatant->isDeadOnSaves())->toBeTrue()
        ->and($combatant->isDying())->toBeFalse();
});

it('writes nothing when a standing combatant is marked', function () {
    $encounter = aFight();
    $combatant = Combatant::factory()->inEncounter($encounter)->withHealth(20)->create();

    trackerFor($encounter)->call('deathSaveFailure', $combatant->id);

    expect($combatant->refresh()->death_save_failures)->toBe(0);
});

it('clears the marks when healing lifts the row back above nought', function () {
    $encounter = aFight();
    $combatant = Combatant::factory()->inEncounter($encounter)->down()->create([
        'death_save_successes' => 1,
        'death_save_failures' => 2,
    ]);

    app(ApplyDamage::class)->handle($combatant, -4);

    expect($combatant->refresh()->hp)->toBe(4)
        ->and($combatant->death_save_successes)->toBe(0)
        ->and($combatant->death_save_failures)->toBe(0);
});

it('brings a creature its legendary actions when it comes out of the compendium', function () {
    $campaign = Campaign::factory()->create(['ruleset' => Ruleset::Srd5e2024]);
    $encounter = aFight($campaign);

    $statBlock = StatBlock::factory()->named('Aboleth')->withLegendaryActions(3)->create();

    trackerFor($encounter)->call('addFromCompendium', $statBlock->id);

    $combatant = $encounter->combatants()->where('name', 'Aboleth')->sole();

    expect($combatant->legendary_actions_max)->toBe(3)
        ->and($combatant->legendary_actions_left)->toBe(3);
});

it('spends a use, and stops at none left', function () {
    $encounter = aFight();
    $combatant = Combatant::factory()->inEncounter($encounter)->withLegendaryActions(2)->create();

    $tracker = trackerFor($encounter);

    foreach (range(1, 4) as $ignored) {
        $tracker->call('spendLegendaryAction', $combatant->id);
    }

    expect($combatant->refresh()->legendary_actions_left)->toBe(0)
        ->and($combatant->legendary_actions_max)->toBe(2);
});

it('gives them back when the turn marker reaches that creature', function () {
    $encounter = aFight();
    $first = Combatant::factory()->inEncounter($encounter, 0)->create();
    $dragon = Combatant::factory()->inEncounter($encounter, 1)->withLegendaryActions(3)->create();

    $dragon->update(['legendary_actions_left' => 0]);

    $tracker = trackerFor($encounter);

    $tracker->call('nextTurn');
    expect($encounter->refresh()->active_combatant_id)->toBe($first->id)
        ->and($dragon->refresh()->legendary_actions_left)->toBe(0);

    $tracker->call('nextTurn');
    expect($encounter->refresh()->active_combatant_id)->toBe($dragon->id)
        ->and($dragon->refresh()->legendary_actions_left)->toBe(3);
});

it('leaves a row that never had legendary actions alone', function () {
    $encounter = aFight();
    $combatant = Combatant::factory()->inEncounter($encounter)->create();

    trackerFor($encounter)->call('spendLegendaryAction', $combatant->id);

    expect($combatant->refresh()->legendary_actions_left)->toBeNull()
        ->and($combatant->hasLegendaryActions())->toBeFalse();
});

it('takes a lair action in the GM own words, on a count they choose', function () {
    $encounter = aFight();

    trackerFor($encounter)
        ->call('openLair')
        ->set('lairNote', 'The cavern floor buckles.')
        ->set('lairInitiative', 18)
        ->call('saveLair')
        ->assertHasNoErrors();

    expect($encounter->refresh()->hasLairAction())->toBeTrue()
        ->and($encounter->lair_action_note)->toBe('The cavern floor buckles.')
        ->and($encounter->lairInitiative())->toBe(18);
});

it('defaults the lair to twenty and clears both columns when the words go', function () {
    $encounter = aFight();

    trackerFor($encounter)->call('openLair')->set('lairNote', 'The walls close in.')->call('saveLair');

    expect($encounter->refresh()->lairInitiative())->toBe(Encounter::DEFAULT_LAIR_INITIATIVE);

    trackerFor($encounter)->call('openLair')->set('lairNote', '')->call('saveLair');

    expect($encounter->refresh()->hasLairAction())->toBeFalse()
        ->and($encounter->lair_initiative)->toBeNull();
});

it('puts the lair marker where it would fall if the order were sorted', function () {
    $encounter = aFight();
    $encounter->update(['lair_action_note' => 'The floor buckles.', 'lair_initiative' => 15]);

    $combatants = collect([
        Combatant::factory()->inEncounter($encounter, 0)->withInitiative(22)->create(),
        Combatant::factory()->inEncounter($encounter, 1)->withInitiative(18)->create(),
        Combatant::factory()->inEncounter($encounter, 2)->withInitiative(9)->create(),
    ]);

    expect($encounter->lairMarkerIndex($combatants))->toBe(2);
});

it('puts the marker last when everybody beat it, and first when nobody has rolled', function () {
    $encounter = aFight();
    $encounter->update(['lair_action_note' => 'The floor buckles.', 'lair_initiative' => 5]);

    $beaten = collect([
        Combatant::factory()->inEncounter($encounter, 0)->withInitiative(22)->create(),
        Combatant::factory()->inEncounter($encounter, 1)->withInitiative(11)->create(),
    ]);

    expect($encounter->lairMarkerIndex($beaten))->toBe(2);

    $unrolled = collect([Combatant::factory()->inEncounter($encounter, 2)->create()]);

    expect($encounter->lairMarkerIndex($unrolled))->toBe(0);
});

it('shows no marker at all when there is no lair action', function () {
    $encounter = aFight();

    expect($encounter->lairMarkerIndex(collect()))->toBeNull();
});

it('renders the lair action in the turn order', function () {
    $encounter = aFight();
    $encounter->update(['lair_action_note' => 'The cavern floor buckles.', 'lair_initiative' => 20]);

    Combatant::factory()->inEncounter($encounter)->withInitiative(14)->create();

    trackerFor($encounter)
        ->assertSee('Lair action')
        ->assertSee('The cavern floor buckles.');
});
