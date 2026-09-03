<?php

use App\Enums\CampaignRole;
use App\Livewire\Encounters\Index;
use App\Livewire\Encounters\Show;
use App\Livewire\Encounters\Tracker;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use Livewire\Livewire;

it('404s the encounter index for a player and a spectator', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    $member = memberOf($campaign, $role);

    Livewire::actingAs($member)
        ->test(Index::class, ['campaign' => $campaign])
        ->assertForbidden();

    $this->actingAs($member)->get(route('encounters.index', $campaign))->assertForbidden();
})->with([CampaignRole::Player, CampaignRole::Spectator]);

it('404s an encounter page for a player, with nothing of the fight in the response', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $encounter = Encounter::factory()->for($campaign)->create(['name' => 'Ambush at the bridge']);
    Combatant::factory()->inEncounter($encounter)->withHealth(59)->create(['name' => 'Ogre chief']);

    $response = $this->actingAs($player)->get(route('encounters.show', [$campaign, $encounter->id]));

    $response->assertNotFound()
        ->assertDontSee('Ambush at the bridge')
        ->assertDontSee('Ogre chief')
        ->assertDontSee('59');
});

it('404s the tracker component itself for a player', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $encounter = Encounter::factory()->for($campaign)->create();

    Livewire::actingAs($player)
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->assertForbidden();
});

it('lets a co-GM run the fight', function () {
    $campaign = Campaign::factory()->create();
    $coGm = memberOf($campaign, CampaignRole::CoGm);
    $encounter = Encounter::factory()->for($campaign)->create();

    Livewire::actingAs($coGm)
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter])
        ->set('newName', 'Goblin')
        ->call('addCombatant')
        ->assertHasNoErrors();

    expect($encounter->combatants()->count())->toBe(1);
});

it('stops a co-GM demoted mid-encounter on their next poll', function () {
    $campaign = Campaign::factory()->create();
    $coGm = memberOf($campaign, CampaignRole::CoGm);
    $encounter = Encounter::factory()->for($campaign)->create();
    $combatant = Combatant::factory()->inEncounter($encounter)->withHealth(30)->create();

    $component = Livewire::actingAs($coGm)
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter]);

    $campaign->members()->where('user_id', $coGm->id)->update(['role' => CampaignRole::Player]);

    $component->call('nextTurn')->assertForbidden();

    expect($encounter->refresh()->active_combatant_id)->toBeNull()
        ->and($combatant->refresh()->hp)->toBe(30);
});

it('stops a co-GM removed from the campaign entirely', function () {
    $campaign = Campaign::factory()->create();
    $coGm = memberOf($campaign, CampaignRole::CoGm);
    $encounter = Encounter::factory()->for($campaign)->create();

    $component = Livewire::actingAs($coGm)
        ->test(Tracker::class, ['campaign' => $campaign, 'encounter' => $encounter]);

    $campaign->members()->where('user_id', $coGm->id)->delete();

    $component->call('nextTurn')->assertStatus(404);
});

it('404s an encounter in another campaign', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $stranger = Encounter::factory()->for($other)->create();

    $this->actingAs(ownerOf($campaign))
        ->get(route('encounters.show', [$campaign, $stranger->id]))
        ->assertNotFound();
});

it('404s an id that is not an encounter at all', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(ownerOf($campaign))
        ->get(route('encounters.show', [$campaign, 'not-a-ulid']))
        ->assertNotFound();
});

it('keeps the encounter index scoped to the campaign', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();

    Encounter::factory()->for($campaign)->create(['name' => 'Ours']);
    Encounter::factory()->for($other)->create(['name' => 'Theirs']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->assertSee('Ours')
        ->assertDontSee('Theirs');
});

it('shows the encounter page to a GM', function () {
    $campaign = Campaign::factory()->create();
    $encounter = Encounter::factory()->for($campaign)->create(['name' => 'Ambush at the bridge']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'encounterId' => $encounter->id])
        ->assertOk()
        ->assertSee('Ambush at the bridge');
});
