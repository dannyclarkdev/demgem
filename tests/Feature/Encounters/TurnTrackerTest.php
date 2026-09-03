<?php

use App\Actions\Encounters\NextTurn;
use App\Enums\EncounterStatus;
use App\Livewire\Encounters\Tracker;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use Illuminate\Support\Collection;
use Livewire\Livewire;

/**
 * @return array{0: Campaign, 1: Encounter, 2: Collection<int, Combatant>}
 */
function fightOfThree(): array
{
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    $rows = collect(['First', 'Second', 'Third'])
        ->map(fn (string $name, int $index) => Combatant::factory()->inEncounter($encounter, $index)->create(['name' => $name]));

    return [$campaign, $encounter, $rows];
}

it('starts at the top of the order and opens round one', function () {
    [, $encounter, $rows] = fightOfThree();

    app(NextTurn::class)->handle($encounter);

    expect($encounter->refresh()->active_combatant_id)->toBe($rows[0]->id)
        ->and($encounter->round)->toBe(1)
        ->and($encounter->status)->toBe(EncounterStatus::Active);
});

it('advances by position', function () {
    [, $encounter, $rows] = fightOfThree();
    $nextTurn = app(NextTurn::class);

    $nextTurn->handle($encounter);
    $nextTurn->handle($encounter);

    expect($encounter->refresh()->active_combatant_id)->toBe($rows[1]->id)
        ->and($encounter->round)->toBe(1);
});

it('starts a new round when the order wraps', function () {
    [, $encounter, $rows] = fightOfThree();
    $nextTurn = app(NextTurn::class);

    foreach (range(1, 4) as $ignored) {
        $nextTurn->handle($encounter);
    }

    expect($encounter->refresh()->active_combatant_id)->toBe($rows[0]->id)
        ->and($encounter->round)->toBe(2);
});

it('does nothing in an empty fight', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();

    app(NextTurn::class)->handle($encounter);

    expect($encounter->refresh()->active_combatant_id)->toBeNull()
        ->and($encounter->round)->toBe(0);
});

it('starts again from the top when the active combatant is removed', function () {
    [$campaign, $encounter, $rows] = fightOfThree();
    $nextTurn = app(NextTurn::class);

    $nextTurn->handle($encounter);
    $nextTurn->handle($encounter);

    expect($encounter->refresh()->active_combatant_id)->toBe($rows[1]->id);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('removeCombatant', $rows[1]->id);

    // RemoveCombatant clears the marker, because active_combatant_id carries no
    // foreign key to do it for us.
    expect($encounter->refresh()->active_combatant_id)->toBeNull();

    $nextTurn->handle($encounter);

    expect($encounter->refresh()->active_combatant_id)->toBe($rows[0]->id);
});

it('survives a refresh mid-fight', function () {
    [$campaign, $encounter, $rows] = fightOfThree();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('nextTurn')
        ->call('nextTurn');

    // A fresh mount is what a browser refresh does.
    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter->fresh()])
        ->assertSet('encounter.active_combatant_id', $rows[1]->id)
        ->assertSee('Round 1')
        ->assertSee('Second');
});

it('ends and reopens the fight in both directions', function () {
    [$campaign, $encounter] = fightOfThree();

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter]);

    $component->call('nextTurn')->call('endEncounter');

    expect($encounter->refresh()->status)->toBe(EncounterStatus::Done)
        ->and($encounter->round)->toBe(1);

    $component->call('reopenEncounter');

    expect($encounter->refresh()->status)->toBe(EncounterStatus::Active);
});

it('resets the round and the marker', function () {
    [$campaign, $encounter] = fightOfThree();

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter]);

    $component->call('nextTurn')->call('nextTurn')->call('resetEncounter');

    expect($encounter->refresh()->active_combatant_id)->toBeNull()
        ->and($encounter->round)->toBe(0)
        ->and($encounter->status)->toBe(EncounterStatus::Planning);
});

it('keeps the marker on the same combatant through a reorder', function () {
    [$campaign, $encounter, $rows] = fightOfThree();

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter]);

    $component->call('nextTurn')->call('nextTurn');

    expect($encounter->refresh()->active_combatant_id)->toBe($rows[1]->id);

    $component->call('reorder', $rows[1]->id, 0);

    expect($encounter->refresh()->active_combatant_id)->toBe($rows[1]->id);
});
