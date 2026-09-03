<?php

use App\Enums\EncounterStatus;
use App\Livewire\Encounters\Index;
use App\Livewire\Encounters\Show;
use App\Livewire\Sessions\Run;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\GameSession;
use Livewire\Livewire;

it('creates an encounter and goes straight to it', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);

    Livewire::actingAs($owner)
        ->test(Index::class, ['campaign' => $campaign])
        ->set('newName', 'Ambush at the bridge')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    $encounter = Encounter::query()->sole();

    expect($encounter->name)->toBe('Ambush at the bridge')
        ->and($encounter->status)->toBe(EncounterStatus::Planning)
        ->and($encounter->round)->toBe(0)
        ->and($encounter->game_session_id)->toBeNull()
        ->and($encounter->created_by)->toBe($owner->id);
});

it('requires a name', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->set('newName', '')
        ->call('create')
        ->assertHasErrors('newName');

    expect(Encounter::query()->count())->toBe(0);
});

it('renames an encounter', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create(['name' => 'Old name']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'encounterId' => $encounter->id])
        ->set('name', 'Ambush at the bridge')
        ->call('rename')
        ->assertHasNoErrors();

    expect($encounter->refresh()->name)->toBe('Ambush at the bridge');
});

it('hard deletes an encounter and its combatants', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create();
    Combatant::factory()->count(3)->inEncounter($encounter)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->call('delete', $encounter->id);

    expect(Encounter::query()->count())->toBe(0)
        ->and(Combatant::query()->count())->toBe(0);
});

it('sorts the index with the fight in play first', function () {
    $campaign = Campaign::factory()->create();

    Encounter::factory()->for($campaign)->done()->create(['name' => 'Finished']);
    Encounter::factory()->for($campaign)->create(['name' => 'Planned']);
    Encounter::factory()->for($campaign)->active()->create(['name' => 'Running']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->assertViewHas('encounters', fn ($encounters) => $encounters->pluck('name')->all() === ['Running', 'Planned', 'Finished']);
});

it('starts an encounter from the Run screen and links it to the session', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(4)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Run::class, ['campaign' => $campaign, 'number' => 4])
        ->assertSee('Start an encounter')
        ->call('startEncounter');

    $encounter = Encounter::query()->sole();

    expect($encounter->game_session_id)->toBe($session->id)
        ->and($encounter->name)->toBe('Session 4 encounter');
});

it('shows the session encounters on the Run screen and nobody else\'s', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(4)->create();
    $other = GameSession::factory()->for($campaign)->number(5)->create();

    Encounter::factory()->inSession($session)->create(['name' => 'Tonight']);
    Encounter::factory()->inSession($other)->create(['name' => 'Next week']);
    Encounter::factory()->for($campaign)->create(['name' => 'No session']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Run::class, ['campaign' => $campaign, 'number' => 4])
        ->assertSee('Tonight')
        ->assertDontSee('Next week')
        ->assertDontSee('No session');
});

it('keeps an encounter linked to a session that is soft deleted', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(4)->create();
    $encounter = Encounter::factory()->inSession($session)->create();

    $session->delete();

    // Sessions soft-delete, so nullOnDelete never fires and the link survives.
    expect($encounter->refresh()->game_session_id)->toBe($session->id);
});
