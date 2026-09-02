<?php

use App\Enums\CampaignRole;
use App\Livewire\Search;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;

it('finds entities by name and body, scoped to the campaign and the viewer', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Salt Cathedral', 'body' => 'Where the abbess preaches.']);
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Harbor Watch', 'body' => 'They guard the salt road.']);
    Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'Salt Crypt']);
    Entity::factory()->forPlayers()->create(['name' => 'Salt Flats Elsewhere']);

    $this->actingAs($player)
        ->get(route('search', [$campaign, 'q' => 'salt']))
        ->assertOk()
        ->assertSee('Salt Cathedral')
        ->assertSee('Harbor Watch')
        ->assertDontSee('Salt Crypt')
        ->assertDontSee('Salt Flats Elsewhere');

    $this->actingAs(ownerOf($campaign))
        ->get(route('search', [$campaign, 'q' => 'salt']))
        ->assertSee('Salt Crypt');
});

it('never matches GM notes', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->forPlayers()->withDmNotes('The password is swordfish.')->create(['name' => 'Gatekeeper', 'body' => 'Grumpy.']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('search', [$campaign, 'q' => 'swordfish']))
        ->assertOk()
        ->assertDontSee('Gatekeeper');
});

it('shows a prompt with no query and renders the search page', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(ownerOf($campaign))
        ->get(route('search', $campaign))
        ->assertOk()
        ->assertSeeLivewire(Search::class)
        ->assertSee('Search the campaign');
});

it('returns 404 for a non-member', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('search', [$campaign, 'q' => 'x']))
        ->assertNotFound();
});
