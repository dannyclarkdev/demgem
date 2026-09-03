<?php

use App\Actions\Encounters\AddCombatants;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\PrepRole;
use App\Livewire\Encounters\Tracker;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\GameSession;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

it('adds a bare name typed by hand', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->set('newName', 'Bandit captain')
        ->set('newHp', 65)
        ->set('newAc', 15)
        ->call('addCombatant')
        ->assertHasNoErrors()
        ->assertSet('newName', '');

    $combatant = $encounter->combatants()->sole();

    expect($combatant->name)->toBe('Bandit captain')
        ->and($combatant->hp)->toBe(65)
        ->and($combatant->max_hp)->toBe(65)
        ->and($combatant->ac)->toBe(15)
        ->and($combatant->entity_id)->toBeNull()
        ->and($combatant->position)->toBe(0);
});

it('numbers a quantity so the GM can name them out loud', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->set('newName', 'Goblin')
        ->set('newQuantity', 4)
        ->set('newHp', 7)
        ->call('addCombatant');

    expect($encounter->combatants()->pluck('name')->all())->toBe(['Goblin 1', 'Goblin 2', 'Goblin 3', 'Goblin 4'])
        ->and($encounter->combatants()->pluck('position')->all())->toBe([0, 1, 2, 3])
        ->and($encounter->combatants()->pluck('hp')->unique()->all())->toBe([7]);
});

it('does not number a quantity of one', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    app(AddCombatants::class)->handle($encounter, 'Ogre', 1);

    expect($encounter->combatants()->sole()->name)->toBe('Ogre');
});

it('caps the quantity', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->set('newName', 'Rat')
        ->set('newQuantity', 21)
        ->call('addCombatant')
        ->assertHasErrors('newQuantity');

    expect($encounter->combatants()->count())->toBe(0);
});

it('adds the whole party in one click', function () {
    $campaign = Campaign::factory()->create();
    $tobin = memberOf($campaign, CampaignRole::Player);
    $mara = memberOf($campaign, CampaignRole::Player);

    Entity::factory()->for($campaign)->pcOf($tobin)->create(['name' => 'Tobin']);
    Entity::factory()->for($campaign)->pcOf($mara)->create(['name' => 'Mara']);
    Entity::factory()->for($campaign)->type(EntityType::Character)->create(['name' => 'Not a PC']);

    $encounter = Encounter::factory()->for($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('addParty');

    expect($encounter->combatants()->pluck('name')->all())->toBe(['Mara', 'Tobin']);
});

it('adds a monster from the session prep in one click', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(3)->create();
    $ogre = Entity::factory()->for($campaign)->type(EntityType::Character)->create(['name' => 'Ogre chief']);

    $session->entities()->attach($ogre->id, ['role' => PrepRole::Monster->value, 'position' => 0]);

    $encounter = Encounter::factory()->inSession($session)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->assertSee('Ogre chief')
        ->call('addEntity', $ogre->id);

    $combatant = $encounter->combatants()->sole();

    expect($combatant->name)->toBe('Ogre chief')
        ->and($combatant->entity_id)->toBe($ogre->id);
});

it('keeps a combatant whole after its entity is deleted', function () {
    $campaign = Campaign::factory()->create();
    $ogre = Entity::factory()->for($campaign)->create(['name' => 'Ogre chief']);
    $encounter = Encounter::factory()->for($campaign)->create();

    app(AddCombatants::class)->handle($encounter, $ogre->name, 1, $ogre, 59, 16);

    $ogre->delete();

    $combatant = $encounter->combatants()->with('entity')->sole();

    expect($combatant->name)->toBe('Ogre chief')
        ->and($combatant->hp)->toBe(59)
        ->and($combatant->ac)->toBe(16)
        ->and($combatant->entity)->toBeNull()
        ->and($combatant->isPlayerCharacter())->toBeFalse();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->assertSee('Ogre chief');
});

it('appends new combatants to the end of the order', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    Combatant::factory()->inEncounter($encounter, 0)->create(['name' => 'First']);

    app(AddCombatants::class)->handle($encounter, 'Second', 1);

    expect($encounter->combatants()->pluck('name')->all())->toBe(['First', 'Second'])
        ->and($encounter->combatants()->pluck('position')->all())->toBe([0, 1]);
});

it('removes a combatant', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('removeCombatant', $combatant->id);

    expect($encounter->combatants()->count())->toBe(0);
});

it('refuses a combatant from another encounter', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $other = Encounter::factory()->for($campaign)->create();
    $stranger = Combatant::factory()->inEncounter($other)->create();

    expect(fn () => Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('removeCombatant', $stranger->id))
        ->toThrow(ModelNotFoundException::class);

    expect($other->combatants()->count())->toBe(1);
});
