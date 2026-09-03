<?php

use App\Actions\Encounters\ApplyDamage;
use App\Actions\Encounters\SetConditions;
use App\Livewire\Encounters\Tracker;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use Livewire\Livewire;

it('takes damage', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->withHealth(30)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('openDamage', $combatant->id)
        ->set('damage', '7')
        ->call('applyDamage', $combatant->id, 1)
        ->assertSet('damageFor', null);

    expect($combatant->refresh()->hp)->toBe(23);
});

it('heals', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->withHealth(10, 30)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('openDamage', $combatant->id)
        ->set('damage', '5')
        ->call('applyDamage', $combatant->id, -1);

    expect($combatant->refresh()->hp)->toBe(15);
});

it('clamps at zero rather than going negative', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->withHealth(4)->create();

    app(ApplyDamage::class)->handle($combatant, 40);

    expect($combatant->refresh()->hp)->toBe(0)
        ->and($combatant->isDown())->toBeTrue();
});

it('clamps healing at max hp', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->withHealth(20, 25)->create();

    app(ApplyDamage::class)->handle($combatant, -100);

    expect($combatant->refresh()->hp)->toBe(25);
});

it('ignores a combatant the GM never gave hit points', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->create();

    app(ApplyDamage::class)->handle($combatant, 10);

    expect($combatant->refresh()->hp)->toBeNull()
        ->and($combatant->isDown())->toBeFalse();
});

it('ignores an empty damage box', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->withHealth(30)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('openDamage', $combatant->id)
        ->set('damage', '')
        ->call('applyDamage', $combatant->id, 1);

    expect($combatant->refresh()->hp)->toBe(30);
});

it('adds and removes conditions', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->create();

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('openConditions', $combatant->id)
        ->set('newCondition', 'Prone')
        ->call('addCondition', $combatant->id)
        ->assertSet('newCondition', '');

    expect($combatant->refresh()->conditionList())->toBe(['Prone']);

    $component->set('newCondition', 'Frightened')->call('addCondition', $combatant->id);

    expect($combatant->refresh()->conditionList())->toBe(['Prone', 'Frightened']);

    $component->call('removeCondition', $combatant->id, 'prone');

    expect($combatant->refresh()->conditionList())->toBe(['Frightened']);
});

it('accepts a condition from any system, not a fixed list', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('openConditions', $combatant->id)
        ->set('newCondition', 'Marked by the Hound')
        ->call('addCondition', $combatant->id);

    expect($combatant->refresh()->conditionList())->toBe(['Marked by the Hound']);
});

it('never stores the same condition twice', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->create();

    app(SetConditions::class)->handle($combatant, ['Prone', 'prone', 'PRONE']);

    expect($combatant->refresh()->conditionList())->toBe(['Prone']);
});

it('caps the number of conditions and their length', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->create();

    $many = array_map(fn (int $n) => "Condition {$n}", range(1, 20));

    app(SetConditions::class)->handle($combatant, $many);

    expect($combatant->refresh()->conditionList())->toHaveCount(Combatant::MAX_CONDITIONS);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('openConditions', $combatant->id)
        ->set('newCondition', str_repeat('a', Combatant::MAX_CONDITION_LENGTH + 1))
        ->call('addCondition', $combatant->id)
        ->assertHasErrors('newCondition');
});
