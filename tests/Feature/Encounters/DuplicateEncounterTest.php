<?php

use App\Actions\Encounters\DuplicateEncounter;
use App\Enums\EncounterStatus;
use App\Livewire\Encounters\Tracker;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use Livewire\Livewire;

/**
 * A copy is the fight before it started. Everything a GM assembled comes across, and
 * everything that happened once the dice were out does not.
 */
function aFightWorthCopying(Campaign $campaign): Encounter
{
    $encounter = Encounter::factory()->for($campaign)->create([
        'name' => 'Ambush on the coast road',
        'status' => EncounterStatus::Active,
        'round' => 4,
        'lair_action_note' => 'The dunes shift.',
        'lair_initiative' => 20,
    ]);

    $pc = Entity::factory()->for($campaign)->create(['is_pc' => true, 'level' => 3]);

    Combatant::factory()->inEncounter($encounter, 0)->forEntity($pc)->shownToPlayers()
        ->withInitiative(18)->withHealth(11, 24)->create();

    Combatant::factory()->inEncounter($encounter, 1)->withLegendaryActions(3)
        ->withInitiative(12)->withHealth(0, 40)->create([
            'name' => 'Sand drake',
            'ac' => 15,
            'initiative_bonus' => 2,
            'conditions' => ['Prone'],
            'concentrating_on' => 'Blur',
            'death_save_failures' => 2,
            'legendary_actions_left' => 0,
        ]);

    $encounter->update(['active_combatant_id' => $encounter->combatants()->first()?->id]);

    return $encounter;
}

it('carries everything the GM assembled', function () {
    $campaign = Campaign::factory()->create();
    $encounter = aFightWorthCopying($campaign);

    $copy = app(DuplicateEncounter::class)->handle($encounter);

    expect($copy->id)->not->toBe($encounter->id)
        ->and($copy->name)->toBe('Ambush on the coast road (copy)')
        ->and($copy->lair_action_note)->toBe('The dunes shift.')
        ->and($copy->lair_initiative)->toBe(20)
        ->and($copy->combatants()->count())->toBe(2);

    $drake = $copy->combatants()->where('name', 'Sand drake')->sole();

    expect($drake->ac)->toBe(15)
        ->and($drake->max_hp)->toBe(40)
        ->and($drake->initiative_bonus)->toBe(2)
        ->and($drake->legendary_actions_max)->toBe(3);
});

it('leaves behind everything that happened after the dice came out', function () {
    $campaign = Campaign::factory()->create();
    $encounter = aFightWorthCopying($campaign);

    $copy = app(DuplicateEncounter::class)->handle($encounter);
    $drake = $copy->combatants()->where('name', 'Sand drake')->sole();

    expect($copy->status)->toBe(EncounterStatus::Planning)
        ->and($copy->round)->toBe(0)
        ->and($copy->active_combatant_id)->toBeNull()
        ->and($copy->game_session_id)->toBeNull()
        ->and($drake->initiative)->toBeNull()
        ->and($drake->conditionList())->toBe([])
        ->and($drake->concentrating_on)->toBeNull()
        ->and($drake->death_save_failures)->toBe(0);
});

it('stands the fight back up at full health', function () {
    $campaign = Campaign::factory()->create();
    $encounter = aFightWorthCopying($campaign);

    $copy = app(DuplicateEncounter::class)->handle($encounter);
    $drake = $copy->combatants()->where('name', 'Sand drake')->sole();

    expect($drake->hp)->toBe(40)
        ->and($drake->isDown())->toBeFalse()
        ->and($drake->legendary_actions_left)->toBe(3);
});

it('keeps which rows the party could already see', function () {
    $campaign = Campaign::factory()->create();
    $encounter = aFightWorthCopying($campaign);

    $copy = app(DuplicateEncounter::class)->handle($encounter);

    expect($copy->combatants()->where('player_visible', true)->count())->toBe(1)
        ->and($copy->combatants()->where('name', 'Sand drake')->sole()->player_visible)->toBeFalse();
});

it('leaves the fight it copied untouched', function () {
    $campaign = Campaign::factory()->create();
    $encounter = aFightWorthCopying($campaign);

    app(DuplicateEncounter::class)->handle($encounter);

    expect($encounter->refresh()->round)->toBe(4)
        ->and($encounter->status)->toBe(EncounterStatus::Active)
        ->and($encounter->combatants()->where('name', 'Sand drake')->sole()->death_save_failures)->toBe(2);
});

it('lands the GM on the copy', function () {
    $campaign = Campaign::factory()->create();
    $encounter = aFightWorthCopying($campaign);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->call('duplicate')
        ->assertRedirect();

    expect(Encounter::query()->count())->toBe(2);
});
