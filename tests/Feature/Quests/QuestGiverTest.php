<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Entities\Form;
use App\Livewire\Entities\Show;
use App\Models\Campaign;
use App\Models\Entity;
use Livewire\Livewire;

it('shows the giver to a player when they can see that entity', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $baron = Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()->create(['name' => 'Baron Kell']);
    $quest = Entity::factory()->for($campaign)->quest()->forPlayers()->givenBy($baron)->create();

    Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'quests', 'slug' => $quest->slug])
        ->assertViewHas('giver', fn (?Entity $giver) => $giver?->is($baron))
        ->assertSee('Baron Kell');
});

it('never names a GM-only giver on a quest the party can read', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $spy = Entity::factory()->for($campaign)->type(EntityType::Character)->dmOnly()->create(['name' => 'Wren the Quiet']);
    $quest = Entity::factory()->for($campaign)->quest()->forPlayers()->givenBy($spy)->create(['name' => 'The Ledger']);

    $component = Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'quests', 'slug' => $quest->slug]);

    $component->assertViewHas('giver', null)
        ->assertDontSee('Wren the Quiet')
        ->assertDontSee('Given by');

    // The snapshot ships to the browser too, so the name must be absent from it.
    expect(json_encode($component->snapshot))->not->toContain('Wren the Quiet');
});

it('shows a GM the giver they hid from the party', function () {
    $campaign = Campaign::factory()->create();

    $spy = Entity::factory()->for($campaign)->type(EntityType::Character)->dmOnly()->create(['name' => 'Wren the Quiet']);
    $quest = Entity::factory()->for($campaign)->quest()->forPlayers()->givenBy($spy)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'quests', 'slug' => $quest->slug])
        ->assertSee('Wren the Quiet');
});

it('lets a GM set and clear the giver', function () {
    $campaign = Campaign::factory()->create();
    $baron = Entity::factory()->for($campaign)->type(EntityType::Character)->create(['name' => 'Baron Kell']);
    $quest = Entity::factory()->for($campaign)->quest()->create();

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'quests', 'slug' => $quest->slug])
        ->set('giver_entity_id', $baron->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($quest->refresh()->giver_entity_id)->toBe($baron->id);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'quests', 'slug' => $quest->slug])
        ->set('giver_entity_id', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($quest->refresh()->giver_entity_id)->toBeNull();
});

it('refuses a giver from another campaign', function () {
    $campaign = Campaign::factory()->create();
    $stranger = Entity::factory()->create(['name' => 'Elsewhere']);
    $quest = Entity::factory()->for($campaign)->quest()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'quests', 'slug' => $quest->slug])
        ->set('giver_entity_id', $stranger->id)
        ->call('save')
        ->assertHasErrors('giver_entity_id');
});

it('refuses a quest that gives itself', function () {
    $campaign = Campaign::factory()->create();
    $quest = Entity::factory()->for($campaign)->quest()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'quests', 'slug' => $quest->slug])
        ->set('giver_entity_id', $quest->id)
        ->call('save')
        ->assertHasErrors('giver_entity_id');
});
