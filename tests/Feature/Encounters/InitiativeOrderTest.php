<?php

use App\Actions\Encounters\RollInitiative;
use App\Actions\Encounters\SortByInitiative;
use App\Enums\CampaignRole;
use App\Livewire\Encounters\Tracker;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\DiceRoll;
use App\Models\Encounter;
use App\Models\Entity;
use Livewire\Livewire;

/**
 * @param  array<string, int|null>  $rows  name => initiative
 */
function encounterWith(array $rows, ?Campaign $campaign = null): Encounter
{
    $campaign ??= Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $position = 0;

    foreach ($rows as $name => $initiative) {
        Combatant::factory()->inEncounter($encounter, $position++)->create([
            'name' => $name,
            'initiative' => $initiative,
        ]);
    }

    return $encounter;
}

it('sorts by initiative, highest first', function () {
    $encounter = encounterWith(['Goblin' => 8, 'Fighter' => 19, 'Wolf' => 12]);

    app(SortByInitiative::class)->handle($encounter);

    expect($encounter->combatants()->pluck('name')->all())->toBe(['Fighter', 'Wolf', 'Goblin']);
});

it('puts blank initiative last on every driver', function () {
    // "nulls last" is Postgres-only, so this is the test the CI job proves twice.
    $encounter = encounterWith(['Unrolled' => null, 'Goblin' => 8, 'AlsoUnrolled' => null, 'Fighter' => 19]);

    app(SortByInitiative::class)->handle($encounter);

    $order = $encounter->combatants()->pluck('name')->all();

    expect(array_slice($order, 0, 2))->toBe(['Fighter', 'Goblin'])
        ->and(array_slice($order, 2))->toEqualCanonicalizing(['Unrolled', 'AlsoUnrolled']);
});

it('keeps positions contiguous from zero after a sort', function () {
    $encounter = encounterWith(['A' => 3, 'B' => null, 'C' => 17]);

    app(SortByInitiative::class)->handle($encounter);

    expect($encounter->combatants()->pluck('position')->all())->toBe([0, 1, 2]);
});

it('breaks an initiative tie by the order the GM already had', function () {
    $encounter = encounterWith(['First' => 12, 'Second' => 12, 'Third' => 20]);

    app(SortByInitiative::class)->handle($encounter);

    expect($encounter->combatants()->pluck('name')->all())->toBe(['Third', 'First', 'Second']);
});

it('rolls initiative for everyone the GM runs', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    Combatant::factory()->inEncounter($encounter, 0)->create(['name' => 'Goblin']);
    Combatant::factory()->inEncounter($encounter, 1)->create(['name' => 'Wolf']);

    $rolled = app(RollInitiative::class)->handle($encounter);

    expect($rolled)->toBe(2);

    foreach ($encounter->combatants()->get() as $combatant) {
        expect($combatant->initiative)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(20);
    }
});

it('skips the player characters, because their players roll', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $pc = Entity::factory()->for($campaign)->pcOf($player)->create(['name' => 'Tobin']);

    $encounter = Encounter::factory()->for($campaign)->create();
    $pcRow = Combatant::factory()->inEncounter($encounter, 0)->forEntity($pc)->create();
    $goblin = Combatant::factory()->inEncounter($encounter, 1)->create(['name' => 'Goblin']);

    expect(app(RollInitiative::class)->handle($encounter))->toBe(1)
        ->and($pcRow->refresh()->initiative)->toBeNull()
        ->and($goblin->refresh()->initiative)->not->toBeNull();
});

it('adds the initiative bonus to the roll', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->create(['initiative_bonus' => 5]);

    app(RollInitiative::class)->rollFor($combatant);

    expect($combatant->refresh()->initiative)->toBeGreaterThanOrEqual(6)->toBeLessThanOrEqual(25);
});

it('writes nothing to the dice log when it rolls for a dozen combatants', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    Combatant::factory()->count(12)->inEncounter($encounter)->create();

    app(RollInitiative::class)->handle($encounter);

    expect(DiceRoll::query()->count())->toBe(0);
});

it('reorders by drag and by button and agrees with itself', function () {
    $campaign = Campaign::factory()->create();
    $encounter = encounterWith(['First' => 1, 'Second' => 2, 'Third' => 3], $campaign);
    $rows = $encounter->combatants()->get();

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter]);

    $component->call('reorder', $rows[2]->id, 0);

    expect($encounter->combatants()->pluck('name')->all())->toBe(['Third', 'First', 'Second']);

    $component->call('move', $rows[2]->id, 1);

    expect($encounter->combatants()->pluck('name')->all())->toBe(['First', 'Third', 'Second'])
        ->and($encounter->combatants()->pluck('position')->all())->toBe([0, 1, 2]);
});
