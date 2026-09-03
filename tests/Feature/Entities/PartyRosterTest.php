<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Campaigns\Show as CampaignDashboard;
use App\Livewire\Entities\Index;
use App\Models\Campaign;
use App\Models\Entity;
use Livewire\Livewire;

it('lists the party only, behind the filter', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Entity::factory()->for($campaign)->pcOf($player)->create(['name' => 'Wren']);
    Entity::factory()->for($campaign)->type(EntityType::Character)->create(['name' => 'Harbourmaster Coll']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->assertSee('Wren')
        ->assertSee('Harbourmaster Coll')
        ->set('partyOnly', '1')
        ->assertSee('Wren')
        ->assertDontSee('Harbourmaster Coll');
});

it('reads the filter from the URL, so a filtered party is a link', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Entity::factory()->for($campaign)->pcOf($player)->create(['name' => 'Wren']);
    Entity::factory()->for($campaign)->type(EntityType::Character)->create(['name' => 'Harbourmaster Coll']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.index', [$campaign, 'characters', 'pc' => 1]))
        ->assertOk()
        ->assertSee('Wren')
        ->assertDontSee('Harbourmaster Coll');
});

it('composes the party filter onto the visibility filter, never around it', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $other = memberOf($campaign, CampaignRole::Player);

    Entity::factory()->for($campaign)->pcOf($other)->dmOnly()->create(['name' => 'The Understudy']);
    Entity::factory()->for($campaign)->pcOf($other)->forPlayers()->create(['name' => 'Tobin']);

    Livewire::actingAs($player)
        ->test(Index::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('partyOnly', '1')
        ->assertSee('Tobin')
        ->assertDontSee('The Understudy');
});

it('ignores the party filter on the other five types', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->type(EntityType::Note)->create(['name' => 'House Rules']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign, 'type' => 'notes'])
        ->set('partyOnly', '1')
        ->assertSee('House Rules');
});

it('shows the party with class and level on the dashboard', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Entity::factory()->for($campaign)->pcOf($player)->withRecord('Rogue', 5, null)->forPlayers()->create(['name' => 'Wren']);
    Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()->create(['name' => 'Harbourmaster Coll']);

    Livewire::actingAs($player)
        ->test(CampaignDashboard::class, ['campaign' => $campaign])
        ->assertSee('The party')
        ->assertSee('Wren')
        ->assertSee('Rogue')
        ->assertSee('level 5')
        ->assertDontSee('Harbourmaster Coll');
});

it('keeps another player\'s hidden PC off the dashboard and shows a player their own', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $other = memberOf($campaign, CampaignRole::Player);

    Entity::factory()->for($campaign)->pcOf($other)->dmOnly()->create(['name' => 'The Understudy']);
    Entity::factory()->for($campaign)->pcOf($player)->dmOnly()->create(['name' => 'Wren']);

    Livewire::actingAs($player)
        ->test(CampaignDashboard::class, ['campaign' => $campaign])
        ->assertSee('Wren')
        ->assertDontSee('The Understudy');
});

it('loads the party without a lazy query, which strict mode would throw on', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    Entity::factory()->count(3)->for($campaign)->pcOf($player)->forPlayers()->create();

    Livewire::actingAs($player)
        ->test(CampaignDashboard::class, ['campaign' => $campaign])
        ->assertOk();
});
